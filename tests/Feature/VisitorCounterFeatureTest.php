<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class VisitorCounterFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureVisitorCounterTable();
    }

    private function ensureVisitorCounterTable(): void
    {
        if (Schema::hasTable('visitor_counters')) {
            return;
        }

        Schema::create('visitor_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('member_id')->nullable()->index();
            $table->enum('visitor_type', ['member', 'non_member'])->default('non_member')->index();
            $table->string('visitor_name', 160)->nullable();
            $table->string('member_code_snapshot', 80)->nullable();
            $table->string('purpose', 160)->nullable();
            $table->timestamp('checkin_at')->index();
            $table->timestamp('checkout_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    private function seedContext(): array
    {
        $suffix = substr((string) microtime(true), -6);

        $institutionId = (int) DB::table('institutions')->insertGetId([
            'name' => 'Inst Visitor ' . $suffix,
            'code' => 'INST-VC-' . $suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = (int) DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Cabang Visitor',
            'code' => 'BR-VC-' . $suffix,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $altBranchId = (int) DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Cabang Visitor Alt',
            'code' => 'BR-VC-ALT-' . $suffix,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Admin Visitor',
            'email' => 'admin-visitor-' . $suffix . '@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);

        $memberId = (int) DB::table('members')->insertGetId([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-VC-' . $suffix,
            'full_name' => 'Member Visitor',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId, $altBranchId, $admin, $memberId, 'MBR-VC-' . $suffix];
    }

    public function test_index_can_filter_by_keyword_and_active_only(): void
    {
        [$institutionId, $branchId, $altBranchId, $admin] = $this->seedContext();
        $today = Carbon::today();

        DB::table('visitor_counters')->insert([
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'visitor_type' => 'non_member',
                'visitor_name' => 'Andi Keyword',
                'purpose' => 'Referensi sejarah',
                'checkin_at' => $today->copy()->setTime(9, 0),
                'checkout_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'institution_id' => $institutionId,
                'branch_id' => $altBranchId,
                'visitor_type' => 'non_member',
                'visitor_name' => 'Rina Hidden',
                'purpose' => 'Baca santai',
                'checkin_at' => $today->copy()->setTime(10, 0),
                'checkout_at' => $today->copy()->setTime(11, 0),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.index', [
            'date' => $today->toDateString(),
            'q' => 'Andi',
            'active_only' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('Andi Keyword');
        $response->assertDontSee('Rina Hidden');
    }

    public function test_index_last7_preset_includes_last_7_days_only(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today();

        DB::table('visitor_counters')->insert([
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'visitor_type' => 'non_member',
                'visitor_name' => 'In Last7',
                'purpose' => 'A',
                'checkin_at' => $today->copy()->subDays(6)->setTime(9, 0),
                'checkout_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'visitor_type' => 'non_member',
                'visitor_name' => 'Out Last7',
                'purpose' => 'B',
                'checkin_at' => $today->copy()->subDays(8)->setTime(9, 0),
                'checkout_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.index', [
            'preset' => 'last7',
        ]));

        $response->assertOk();
        $response->assertSee('In Last7');
        $response->assertDontSee('Out Last7');
    }

    public function test_index_per_page_limits_visible_rows(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        for ($i = 1; $i <= 25; $i++) {
            $label = sprintf('PerPageRow-%03d', $i);
            DB::table('visitor_counters')->insert([
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'visitor_type' => 'non_member',
                'visitor_name' => $label,
                'purpose' => 'Uji',
                'checkin_at' => Carbon::parse($today . ' 08:00:00')->addMinutes($i),
                'checkout_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('visitor_counter.index', [
            'date' => $today,
            'per_page' => 20,
        ]));

        $response->assertOk();
        $response->assertSee('PerPageRow-025');
        $response->assertDontSee('PerPageRow-001');
    }

    public function test_store_validation_for_member_and_non_member_rules(): void
    {
        [, , , $admin] = $this->seedContext();

        $this->actingAs($admin)
            ->from(route('visitor_counter.index'))
            ->post(route('visitor_counter.store'), [
                'visitor_type' => 'member',
                'member_code' => '',
                'purpose' => 'Uji',
            ])
            ->assertRedirect(route('visitor_counter.index'))
            ->assertSessionHasErrors(['member_code']);

        $this->actingAs($admin)
            ->from(route('visitor_counter.index'))
            ->post(route('visitor_counter.store'), [
                'visitor_type' => 'non_member',
                'visitor_name' => '',
                'purpose' => 'Uji',
            ])
            ->assertRedirect(route('visitor_counter.index'))
            ->assertSessionHasErrors(['visitor_name']);
    }

    public function test_bulk_checkout_only_updates_matching_active_rows(): void
    {
        [$institutionId, $branchId, $altBranchId, $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $targetId = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Bulk Target',
            'purpose' => 'Cari koleksi',
            'checkin_at' => Carbon::parse($today . ' 08:00:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $untouchedId = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $altBranchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Bulk Untouched',
            'purpose' => 'Cari koleksi',
            'checkin_at' => Carbon::parse($today . ' 09:00:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('visitor_counter.checkout_bulk'), [
                'date' => $today,
                'branch_id' => $branchId,
                'q' => 'Bulk',
            ])
            ->assertRedirect();

        $this->assertNotNull(DB::table('visitor_counters')->where('id', $targetId)->value('checkout_at'));
        $this->assertNull(DB::table('visitor_counters')->where('id', $untouchedId)->value('checkout_at'));
    }

    public function test_export_csv_returns_filtered_rows(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        DB::table('visitor_counters')->insert([
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'visitor_type' => 'non_member',
                'visitor_name' => 'CSV Match',
                'purpose' => 'Referensi',
                'checkin_at' => Carbon::parse($today . ' 08:30:00'),
                'checkout_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'visitor_type' => 'non_member',
                'visitor_name' => 'CSV Skip',
                'purpose' => 'Umum',
                'checkin_at' => Carbon::parse($today . ' 10:00:00'),
                'checkout_at' => Carbon::parse($today . ' 12:00:00'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.export_csv', [
            'date' => $today,
            'q' => 'CSV',
            'active_only' => 1,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', strtolower((string) $response->headers->get('content-type', '')));
        $csv = (string) $response->streamedContent();
        $this->assertStringContainsString('CSV Match', $csv);
        $this->assertStringNotContainsString('CSV Skip', $csv);
    }

    public function test_store_validation_keeps_old_input_for_better_ux(): void
    {
        [, $branchId, , $admin] = $this->seedContext();

        $response = $this->actingAs($admin)
            ->from(route('visitor_counter.index'))
            ->post(route('visitor_counter.store'), [
                'visitor_type' => 'non_member',
                'visitor_name' => '',
                'branch_id' => $branchId,
                'purpose' => 'Belajar',
                'notes' => 'Catatan uji',
            ]);

        $response->assertRedirect(route('visitor_counter.index'));
        $response->assertSessionHasErrors(['visitor_name']);
        $response->assertSessionHasInput('visitor_type', 'non_member');
        $response->assertSessionHasInput('branch_id', (string) $branchId);
        $response->assertSessionHasInput('purpose', 'Belajar');
        $response->assertSessionHasInput('notes', 'Catatan uji');
    }

    public function test_index_filter_values_are_rendered_back_to_view(): void
    {
        [, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($admin)->get(route('visitor_counter.index', [
            'date' => $today,
            'branch_id' => $branchId,
            'q' => 'andi',
            'active_only' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('name="q" value="andi"', false);
        $response->assertSee('name="active_only" value="1" checked', false);
        $response->assertSee('value="' . $branchId . '" selected', false);
    }

    public function test_bulk_checkout_sets_success_flash_message_when_rows_updated(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        DB::table('visitor_counters')->insert([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Flash Target',
            'purpose' => 'Uji flash',
            'checkin_at' => Carbon::parse($today . ' 08:00:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('visitor_counter.checkout_bulk'), [
                'date' => $today,
                'branch_id' => $branchId,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Checkout massal berhasil dijalankan.');
    }

    public function test_bulk_checkout_sets_success_flash_message_when_no_active_rows(): void
    {
        [, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $this->actingAs($admin)
            ->post(route('visitor_counter.checkout_bulk'), [
                'date' => $today,
                'branch_id' => $branchId,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Tidak ada visitor aktif untuk di-checkout.');
    }

    public function test_single_checkout_updates_row_and_sets_success_message(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $id = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Single Checkout',
            'purpose' => 'Uji checkout',
            'checkin_at' => Carbon::parse($today . ' 09:10:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('visitor_counter.checkout', ['id' => $id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Checkout visitor berhasil.');

        $this->assertNotNull(DB::table('visitor_counters')->where('id', $id)->value('checkout_at'));
    }

    public function test_single_checkout_when_already_checked_out_returns_already_message(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $id = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Already Checkout',
            'purpose' => 'Uji already',
            'checkin_at' => Carbon::parse($today . ' 08:00:00'),
            'checkout_at' => Carbon::parse($today . ' 10:00:00'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('visitor_counter.checkout', ['id' => $id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Visitor sudah checkout.');
    }

    public function test_undo_checkout_within_5_minutes_resets_checkout_at(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $id = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Undo Allowed',
            'purpose' => 'Uji undo',
            'checkin_at' => Carbon::parse($today . ' 08:00:00'),
            'checkout_at' => now()->subMinutes(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('visitor_counter.undo_checkout', ['id' => $id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Undo checkout berhasil.');

        $this->assertNull(DB::table('visitor_counters')->where('id', $id)->value('checkout_at'));
    }

    public function test_undo_checkout_after_5_minutes_is_rejected(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $id = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Undo Expired',
            'purpose' => 'Uji undo',
            'checkin_at' => Carbon::parse($today . ' 08:00:00'),
            'checkout_at' => now()->subMinutes(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('visitor_counter.undo_checkout', ['id' => $id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Batas undo checkout (5 menit) sudah lewat.');

        $this->assertNotNull(DB::table('visitor_counters')->where('id', $id)->value('checkout_at'));
    }

    public function test_undo_checkout_when_row_not_checked_out_returns_feedback_message(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $id = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Undo Not Needed',
            'purpose' => 'Uji undo',
            'checkin_at' => Carbon::parse($today . ' 08:00:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('visitor_counter.undo_checkout', ['id' => $id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Visitor belum checkout.');

        $this->assertNull(DB::table('visitor_counters')->where('id', $id)->value('checkout_at'));
    }

    public function test_guest_is_redirected_to_login_for_visitor_counter_pages(): void
    {
        $response = $this->get(route('visitor_counter.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_member_role_is_redirected_from_visitor_counter_pages(): void
    {
        [$institutionId, $branchId] = $this->seedContext();
        $suffix = substr((string) microtime(true), -6);

        $memberUser = User::create([
            'name' => 'Member Blocked',
            'email' => 'member-vc-' . $suffix . '@test.local',
            'password' => Hash::make('password'),
            'role' => 'member',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);

        $this->actingAs($memberUser)
            ->get(route('visitor_counter.index'))
            ->assertRedirect(route('app'));
    }

    public function test_checkout_selected_updates_only_selected_active_rows(): void
    {
        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $idA = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Selected A',
            'purpose' => 'A',
            'checkin_at' => Carbon::parse($today . ' 08:00:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idB = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Selected B',
            'purpose' => 'B',
            'checkin_at' => Carbon::parse($today . ' 08:10:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idC = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Selected C',
            'purpose' => 'C',
            'checkin_at' => Carbon::parse($today . ' 08:20:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('visitor_counter.checkout_selected'), [
                'date' => $today,
                'ids' => [$idA, $idC],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Checkout terpilih berhasil (2 baris).');

        $this->assertNotNull(DB::table('visitor_counters')->where('id', $idA)->value('checkout_at'));
        $this->assertNull(DB::table('visitor_counters')->where('id', $idB)->value('checkout_at'));
        $this->assertNotNull(DB::table('visitor_counters')->where('id', $idC)->value('checkout_at'));
    }

    public function test_checkout_selected_without_ids_returns_feedback_message(): void
    {
        [, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $this->actingAs($admin)
            ->post(route('visitor_counter.checkout_selected'), [
                'date' => $today,
                'branch_id' => $branchId,
                'ids' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Tidak ada baris yang dipilih.');
    }

    public function test_admin_branch_scope_forces_index_to_own_branch_only(): void
    {
        [$institutionId, $branchId, $altBranchId, $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        DB::table('visitor_counters')->insert([
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'visitor_type' => 'non_member',
                'visitor_name' => 'Own Branch Row',
                'purpose' => 'Own',
                'checkin_at' => Carbon::parse($today . ' 08:00:00'),
                'checkout_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'institution_id' => $institutionId,
                'branch_id' => $altBranchId,
                'visitor_type' => 'non_member',
                'visitor_name' => 'Other Branch Row',
                'purpose' => 'Other',
                'checkin_at' => Carbon::parse($today . ' 08:10:00'),
                'checkout_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.index', [
            'date' => $today,
            'branch_id' => $altBranchId,
        ]));

        $response->assertOk();
        $response->assertSee('Own Branch Row');
        $response->assertDontSee('Other Branch Row');
    }

    public function test_admin_cannot_checkout_row_from_other_branch(): void
    {
        [$institutionId, $branchId, $altBranchId, $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        $targetId = (int) DB::table('visitor_counters')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $altBranchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Forbidden Row',
            'purpose' => 'Other branch',
            'checkin_at' => Carbon::parse($today . ' 09:00:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('visitor_counter.checkout', ['id' => $targetId]))
            ->assertNotFound();

        $this->assertNull(DB::table('visitor_counters')->where('id', $targetId)->value('checkout_at'));
    }

    public function test_checkin_writes_audit_log_entry(): void
    {
        if (!Schema::hasTable('audits')) {
            $this->markTestSkipped('Table audits not available.');
        }

        [, $branchId, , $admin] = $this->seedContext();

        $this->actingAs($admin)
            ->from(route('visitor_counter.index'))
            ->post(route('visitor_counter.store'), [
                'visitor_type' => 'non_member',
                'visitor_name' => 'Audit Visitor',
                'branch_id' => $branchId,
                'purpose' => 'Audit Test',
            ])
            ->assertRedirect(route('visitor_counter.index'));

        $this->assertTrue(
            DB::table('audits')
                ->where('action', 'visitor_counter.checkin')
                ->where('module', 'visitor_counter')
                ->where('actor_user_id', (int) $admin->id)
                ->exists()
        );
    }

    public function test_index_can_filter_audit_action_list(): void
    {
        if (!Schema::hasTable('audits')) {
            $this->markTestSkipped('Table audits not available.');
        }

        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        DB::table('visitor_counters')->insert([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Audit Render Seed',
            'purpose' => 'Seed',
            'checkin_at' => Carbon::parse($today . ' 08:00:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('audits')->insert([
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkin',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => null,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkout_bulk',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => null,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.index', [
            'date' => $today,
            'audit_action' => 'visitor_counter.checkout_bulk',
        ]));

        $response->assertOk();
        $response->assertSee('visitor_counter.checkout_bulk');
        $response->assertSee('Riwayat Aksi');
    }

    public function test_index_audit_per_page_limits_visible_audit_rows(): void
    {
        if (!Schema::hasTable('audits')) {
            $this->markTestSkipped('Table audits not available.');
        }

        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        for ($i = 1; $i <= 12; $i++) {
            DB::table('audits')->insert([
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkin',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 900 + $i,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now()->addSeconds($i),
                'updated_at' => now()->addSeconds($i),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('visitor_counter.index', [
            'date' => $today,
            'audit_per_page' => 10,
            'audit_sort' => 'latest',
        ]));

        $response->assertOk();
        $auditRows = $response->viewData('auditRows');
        $this->assertNotNull($auditRows);
        $ids = collect($auditRows->items())->pluck('auditable_id')->map(fn ($id) => (int) $id)->values();
        $this->assertCount(10, $ids);
        $this->assertTrue($ids->contains(912));
        $this->assertFalse($ids->contains(901));
    }

    public function test_export_audit_csv_returns_filtered_audit_rows(): void
    {
        if (!Schema::hasTable('audits')) {
            $this->markTestSkipped('Table audits not available.');
        }

        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        DB::table('visitor_counters')->insert([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Audit Export Seed',
            'purpose' => 'Seed',
            'checkin_at' => Carbon::parse($today . ' 08:30:00'),
            'checkout_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('audits')->insert([
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkin',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => null,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkout_bulk',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => null,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.export_audit_csv', [
            'date' => $today,
            'audit_action' => 'visitor_counter.checkout_bulk',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', strtolower((string) $response->headers->get('content-type', '')));
        $csv = (string) $response->streamedContent();
        $this->assertStringContainsString('visitor_counter.checkout_bulk', $csv);
        $this->assertStringNotContainsString('visitor_counter.checkin', $csv);
    }

    public function test_index_audit_sort_oldest_orders_rows_ascending(): void
    {
        if (!Schema::hasTable('audits')) {
            $this->markTestSkipped('Table audits not available.');
        }

        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        DB::table('audits')->insert([
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkin',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 555001,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkout_bulk',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 555002,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.index', [
            'date' => $today,
            'audit_sort' => 'oldest',
            'audit_per_page' => 10,
        ]));

        $response->assertOk();
        $auditRows = $response->viewData('auditRows');
        $this->assertNotNull($auditRows);
        $ids = collect($auditRows->items())->pluck('auditable_id')->map(fn ($id) => (int) $id)->values();
        $firstIndex = $ids->search(555001);
        $secondIndex = $ids->search(555002);
        $this->assertNotFalse($firstIndex);
        $this->assertNotFalse($secondIndex);
        $this->assertTrue($firstIndex < $secondIndex);
    }

    public function test_export_audit_csv_can_filter_by_keyword(): void
    {
        if (!Schema::hasTable('audits')) {
            $this->markTestSkipped('Table audits not available.');
        }

        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();
        $suffix = substr((string) microtime(true), -6);

        $otherActor = User::create([
            'name' => 'Actor Lain ' . $suffix,
            'email' => 'actor-lain-' . $suffix . '@test.local',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);

        DB::table('audits')->insert([
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkin',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 111,
                'metadata' => json_encode(['branch_id' => $branchId, 'purpose' => 'alpha']),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $otherActor->id,
                'actor_role' => 'staff',
                'action' => 'visitor_counter.checkout_bulk',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 222,
                'metadata' => json_encode(['branch_id' => $branchId, 'purpose' => 'beta']),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.export_audit_csv', [
            'date' => $today,
            'audit_q' => 'checkout_bulk',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', strtolower((string) $response->headers->get('content-type', '')));
        $csv = (string) $response->streamedContent();
        $this->assertStringContainsString('visitor_counter.checkout_bulk', $csv);
        $this->assertStringNotContainsString('visitor_counter.checkin', $csv);
    }

    public function test_export_audit_csv_can_filter_by_actor_role(): void
    {
        if (!Schema::hasTable('audits')) {
            $this->markTestSkipped('Table audits not available.');
        }

        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        DB::table('audits')->insert([
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkin',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 401,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'staff',
                'action' => 'visitor_counter.checkout_bulk',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 402,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.export_audit_csv', [
            'date' => $today,
            'audit_role' => 'admin',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', strtolower((string) $response->headers->get('content-type', '')));
        $csv = (string) $response->streamedContent();
        $this->assertStringContainsString('visitor_counter.checkin', $csv);
        $this->assertStringNotContainsString('visitor_counter.checkout_bulk', $csv);
    }

    public function test_export_audit_csv_respects_oldest_sort(): void
    {
        if (!Schema::hasTable('audits')) {
            $this->markTestSkipped('Table audits not available.');
        }

        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        DB::table('audits')->insert([
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkin',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 701,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ],
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkout_bulk',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 702,
                'metadata' => json_encode(['branch_id' => $branchId]),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.export_audit_csv', [
            'date' => $today,
            'audit_sort' => 'oldest',
        ]));

        $response->assertOk();
        $csv = (string) $response->streamedContent();
        $posCheckin = strpos($csv, 'visitor_counter.checkin');
        $posCheckout = strpos($csv, 'visitor_counter.checkout_bulk');
        $this->assertNotFalse($posCheckin);
        $this->assertNotFalse($posCheckout);
        $this->assertTrue($posCheckin < $posCheckout);
    }

    public function test_export_audit_json_returns_filtered_items(): void
    {
        if (!Schema::hasTable('audits')) {
            $this->markTestSkipped('Table audits not available.');
        }

        [$institutionId, $branchId, , $admin] = $this->seedContext();
        $today = Carbon::today()->toDateString();

        DB::table('audits')->insert([
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkin',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 801,
                'metadata' => json_encode(['branch_id' => $branchId, 'reason' => 'json-a']),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'institution_id' => $institutionId,
                'actor_user_id' => (int) $admin->id,
                'actor_role' => 'admin',
                'action' => 'visitor_counter.checkout_bulk',
                'module' => 'visitor_counter',
                'auditable_type' => 'App\\Models\\VisitorCounter',
                'auditable_id' => 802,
                'metadata' => json_encode(['branch_id' => $branchId, 'reason' => 'json-b']),
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('visitor_counter.export_audit_json', [
            'date' => $today,
            'audit_action' => 'visitor_counter.checkout_bulk',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/json', strtolower((string) $response->headers->get('content-type', '')));
        $response->assertJsonPath('count', 1);
        $response->assertJsonPath('items.0.action', 'visitor_counter.checkout_bulk');
    }
}
