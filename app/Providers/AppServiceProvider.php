<?php

namespace App\Providers;

use App\Support\InteropMetrics;
use App\Models\User;
use App\Models\Biblio;
use App\Models\Item;
use App\Models\AcquisitionRequest;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\Budget;
use App\Policies\KatalogPolicy;
use App\Policies\AcquisitionsPolicy;
use App\Observers\BiblioSearchObserver;
use App\Observers\ItemSearchObserver;
use Database\Seeders\NotobukuSeeder;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->guardDangerousDatabaseCommands();
        $this->configureInteropRateLimiters();
        $this->configurePublicRateLimiters();

        Gate::policy(Biblio::class, KatalogPolicy::class);
        Gate::policy(AcquisitionRequest::class, AcquisitionsPolicy::class);
        Gate::policy(PurchaseOrder::class, AcquisitionsPolicy::class);
        Gate::policy(Vendor::class, AcquisitionsPolicy::class);
        Gate::policy(Budget::class, AcquisitionsPolicy::class);

        Biblio::observe(BiblioSearchObserver::class);
        Item::observe(ItemSearchObserver::class);

        $this->guardUsersTable();

        View::composer('*', function ($view) {
            if (!Auth::check()) {
                return;
            }

            if (!Schema::hasTable('branches')) {
                $view->with([
                    'effectiveBranchId' => 0,
                    'effectiveBranchName' => null,
                    'switchBranches' => collect(),
                ]);
                return;
            }

            $user = Auth::user();

            // Cabang efektif: session (untuk super_admin) -> fallback ke cabang akun
            $effectiveBranchId = (int) session('active_branch_id', (int) ($user->branch_id ?? 0));

            $effectiveBranchName = null;
            if ($effectiveBranchId > 0) {
                $effectiveBranchName = DB::table('branches')
                    ->where('id', $effectiveBranchId)
                    ->value('name');
            }

            // Daftar cabang untuk switch hanya untuk super_admin
            $switchBranches = collect();
            if (($user->role ?? 'member') === 'super_admin') {
                $switchBranches = DB::table('branches')
                    ->select('id', 'name', 'is_active')
                    ->where('is_active', 1)
                    ->orderBy('name')
                    ->get();
            }

            $view->with([
                'effectiveBranchId' => $effectiveBranchId,
                'effectiveBranchName' => $effectiveBranchName,
                'switchBranches' => $switchBranches,
            ]);
        });
    }

    private function configureInteropRateLimiters(): void
    {
        RateLimiter::for('oai-interop', function (Request $request) {
            $ip = (string) ($request->ip() ?? 'unknown');
            $perMinute = (int) config('notobuku.interop.rate_limit.oai.per_minute', 180);
            $perSecond = (int) config('notobuku.interop.rate_limit.oai.per_second', 12);

            return [
                Limit::perMinute(max(1, $perMinute))->by($ip)->response(function () {
                    InteropMetrics::incrementRateLimited('oai');
                    return response('Too Many Requests', Response::HTTP_TOO_MANY_REQUESTS);
                }),
                Limit::perSecond(max(1, $perSecond))->by($ip)->response(function () {
                    InteropMetrics::incrementRateLimited('oai');
                    return response('Too Many Requests', Response::HTTP_TOO_MANY_REQUESTS);
                }),
            ];
        });

        RateLimiter::for('sru-interop', function (Request $request) {
            $ip = (string) ($request->ip() ?? 'unknown');
            $perMinute = (int) config('notobuku.interop.rate_limit.sru.per_minute', 240);
            $perSecond = (int) config('notobuku.interop.rate_limit.sru.per_second', 20);

            return [
                Limit::perMinute(max(1, $perMinute))->by($ip)->response(function () {
                    InteropMetrics::incrementRateLimited('sru');
                    return response('Too Many Requests', Response::HTTP_TOO_MANY_REQUESTS);
                }),
                Limit::perSecond(max(1, $perSecond))->by($ip)->response(function () {
                    InteropMetrics::incrementRateLimited('sru');
                    return response('Too Many Requests', Response::HTTP_TOO_MANY_REQUESTS);
                }),
            ];
        });
    }

    private function configurePublicRateLimiters(): void
    {
        RateLimiter::for('opac-public-search', function (Request $request) {
            $ip = (string) ($request->ip() ?? 'unknown');
            $perMinute = (int) config('notobuku.opac.rate_limit.search.per_minute', 120);
            $perSecond = (int) config('notobuku.opac.rate_limit.search.per_second', 8);

            return [
                Limit::perMinute(max(1, $perMinute))->by($ip),
                Limit::perSecond(max(1, $perSecond))->by($ip),
            ];
        });

        RateLimiter::for('opac-public-detail', function (Request $request) {
            $ip = (string) ($request->ip() ?? 'unknown');
            $perMinute = (int) config('notobuku.opac.rate_limit.detail.per_minute', 180);
            $perSecond = (int) config('notobuku.opac.rate_limit.detail.per_second', 12);

            return [
                Limit::perMinute(max(1, $perMinute))->by($ip),
                Limit::perSecond(max(1, $perSecond))->by($ip),
            ];
        });

        RateLimiter::for('opac-public-seo', function (Request $request) {
            $ip = (string) ($request->ip() ?? 'unknown');
            $perMinute = (int) config('notobuku.opac.rate_limit.seo.per_minute', 30);
            $perSecond = (int) config('notobuku.opac.rate_limit.seo.per_second', 2);

            return [
                Limit::perMinute(max(1, $perMinute))->by($ip),
                Limit::perSecond(max(1, $perSecond))->by($ip),
            ];
        });

        RateLimiter::for('opac-public-write', function (Request $request) {
            $ip = (string) ($request->ip() ?? 'unknown');
            $perMinute = (int) config('notobuku.opac.rate_limit.write.per_minute', 20);
            $perSecond = (int) config('notobuku.opac.rate_limit.write.per_second', 2);

            return [
                Limit::perMinute(max(1, $perMinute))->by($ip),
                Limit::perSecond(max(1, $perSecond))->by($ip),
            ];
        });
    }

    private function guardUsersTable(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        try {
            if (!Schema::hasTable('users')) {
                return;
            }

            $count = User::count();
            if ($count === 0) {
                Log::warning('Users table empty. Auto-seed check running.');

                if (app()->environment('local') && config('notobuku.auto_seed_users_on_empty', true)) {
                    Artisan::call('db:seed', ['--class' => NotobukuSeeder::class, '--force' => true]);
                    Log::info('Auto-seeded users via NotobukuSeeder (local).');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Users table guard failed: ' . $e->getMessage());
        }
    }

    private function guardDangerousDatabaseCommands(): void
    {
        if (!app()->runningInConsole()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $command = strtolower(trim((string) $event->command));
            if (!$this->isDangerousDatabaseCommand($command)) {
                return;
            }

            if (config('notobuku.allow_dangerous_db_commands', false)) {
                return;
            }

            $defaultConnection = (string) config('database.default', '');
            $databaseName = (string) config("database.connections.{$defaultConnection}.database", '');
            $databaseNameLower = strtolower($databaseName);
            $isSqliteMemory = $defaultConnection === 'sqlite' && in_array($databaseName, [':memory:', ''], true);
            $isTestingDbName = str_contains($databaseNameLower, 'test') || str_contains($databaseNameLower, 'testing');
            // Guard ketat: environment testing saja tidak cukup.
            // Tetap wajib target DB bernama test/testing atau sqlite memory.
            $isSafeTarget = $isSqliteMemory || $isTestingDbName;

            if ($isSafeTarget) {
                return;
            }

            throw new RuntimeException(
                "Blocked dangerous command [{$event->command}] on database [{$databaseName}] (connection: {$defaultConnection}). "
                . 'Use a testing DB (e.g. *_test) or set NB_ALLOW_DANGEROUS_DB_COMMANDS=true explicitly.'
            );
        });
    }

    private function isDangerousDatabaseCommand(string $command): bool
    {
        if ($command === '') {
            return false;
        }

        $dangerous = [
            'migrate:fresh',
            'migrate:refresh',
            'migrate:reset',
            'db:wipe',
        ];

        foreach ($dangerous as $item) {
            if ($command === $item || str_starts_with($command, $item . ' ')) {
                return true;
            }
        }

        return false;
    }
}
