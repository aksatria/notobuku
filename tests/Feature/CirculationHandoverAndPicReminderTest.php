<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CirculationHandoverAndPicReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notobuku.circulation.sla.exclude_weekends' => false]);
    }

    public function test_handover_command_generates_markdown_and_unresolved_csv(): void
    {
        Storage::fake('local');

        $date = now()->toDateString();
        $csv = implode("\n", [
            'snapshot_date,exception_type,severity,institution_id,branch_id,loan_id,loan_code,loan_item_id,item_id,barcode,member_id,member_code,detail,days_late,detected_at',
            $date . ',overdue_extreme,warning,1,1,10,L-EX-10,100,1000,BC-EX-10,2000,MBR-EX-10,Overdue aktif melebihi threshold,35,' . now()->subHours(26)->toDateTimeString(),
            $date . ',branch_mismatch_active_loan,critical,1,1,11,L-EX-11,101,1001,BC-EX-11,2001,MBR-EX-11,loan.branch_id=1 item.branch_id=2,0,' . now()->subHours(80)->toDateTimeString(),
        ]);
        Storage::disk('local')->put('reports/circulation-exceptions/circulation-exceptions-' . $date . '.csv', $csv);

        $this->artisan('notobuku:circulation-handover-report', ['--date' => $date])->assertExitCode(0);

        Storage::disk('local')->assertExists('reports/circulation-handover/circulation-handover-' . $date . '.md');
        Storage::disk('local')->assertExists('reports/circulation-handover/circulation-handover-' . $date . '-unresolved.csv');

        $md = Storage::disk('local')->get('reports/circulation-handover/circulation-handover-' . $date . '.md');
        $this->assertStringContainsString('SLA Summary', $md);
        $this->assertStringContainsString('Top Reasons', $md);
    }

    public function test_pic_reminder_command_sends_email_to_owner(): void
    {
        Cache::forget('circulation:pic:reminder:last:1');

        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst PIC',
            'code' => 'INST-PIC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-PIC',
            'name' => 'Cabang PIC',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ownerId = DB::table('users')->insertGetId([
            'name' => 'PIC Ops',
            'email' => 'pic-ops@test.local',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('circulation_exception_acknowledgements')->insert([
            'institution_id' => $institutionId,
            'snapshot_date' => now()->subDay()->toDateString(),
            'fingerprint' => sha1('pic-reminder-1'),
            'exception_type' => 'overdue_extreme',
            'severity' => 'critical',
            'loan_id' => 100,
            'loan_item_id' => 200,
            'item_id' => 300,
            'barcode' => 'BC-PIC-1',
            'member_id' => 400,
            'owner_user_id' => $ownerId,
            'owner_assigned_at' => now()->subHours(30),
            'status' => 'open',
            'created_at' => now()->subHours(30),
            'updated_at' => now()->subHours(30),
        ]);

        config([
            'notobuku.circulation.pic_reminder.sla_hours' => 24,
            'notobuku.circulation.pic_reminder.cooldown_minutes' => 1,
            'notobuku.circulation.pic_reminder.fallback_email_to' => '',
            'mail.default' => 'log',
        ]);

        $this->artisan('notobuku:circulation-pic-reminder')->assertExitCode(0);

        $this->assertNotEmpty(Cache::get('circulation:pic:reminder:last:' . $ownerId, ''));
    }
}
