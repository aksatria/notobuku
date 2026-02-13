<?php

namespace App\Providers;

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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

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
}
