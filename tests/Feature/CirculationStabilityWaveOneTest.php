<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoanTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class CirculationStabilityWaveOneTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Circulation',
            'code' => 'INST-CIR-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-CIR',
            'name' => 'Cabang Circulation',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeAdmin(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'Admin Circulation',
            'email' => 'admin-circulation@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_duplicate_checkout_submit_is_blocked_by_idempotency_guard(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);

        $memberId = DB::table('members')->insertGetId([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-CIR-01',
            'full_name' => 'Member Circulation',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Judul Sirkulasi',
            'material_type' => 'book',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('items')->insert([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'biblio_id' => $biblioId,
            'barcode' => 'BC-CIR-0001',
            'accession_number' => 'ACC-CIR-0001',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'member_id' => $memberId,
            'barcodes' => ['BC-CIR-0001'],
        ];

        $first = $this->actingAs($admin)->post(route('transaksi.pinjam.store'), $payload);
        $first->assertSessionHas('success');

        $second = $this->actingAs($admin)
            ->from(route('transaksi.pinjam.form'))
            ->post(route('transaksi.pinjam.store'), $payload);

        $second->assertRedirect(route('transaksi.pinjam.form'));
        $second->assertSessionHas('error', function ($message) {
            return is_string($message) && str_contains($message, 'Permintaan duplikat');
        });

        $this->assertSame(1, DB::table('loans')->count());
    }

    public function test_loan_transaction_service_enforces_max_items_without_runtime_variable_error(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);

        config([
            'notobuku.loans.max_items' => 1,
            'notobuku.loans.roles' => [],
        ]);

        $memberId = DB::table('members')->insertGetId([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-CIR-02',
            'full_name' => 'Member Limit',
            'member_type' => 'member',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblioOne = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Buku 1',
            'material_type' => 'book',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $biblioTwo = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Buku 2',
            'material_type' => 'book',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemOne = DB::table('items')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'biblio_id' => $biblioOne,
            'barcode' => 'BC-CIR-LIMIT-1',
            'accession_number' => 'ACC-CIR-LIMIT-1',
            'status' => 'borrowed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('items')->insert([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'biblio_id' => $biblioTwo,
            'barcode' => 'BC-CIR-LIMIT-2',
            'accession_number' => 'ACC-CIR-LIMIT-2',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $loanId = DB::table('loans')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'member_id' => $memberId,
            'loan_code' => 'L-CIR-LIMIT',
            'status' => 'open',
            'loaned_at' => now()->subDay(),
            'due_at' => now()->addDays(3),
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('loan_items')->insert([
            'loan_id' => $loanId,
            'item_id' => $itemOne,
            'status' => 'borrowed',
            'borrowed_at' => now()->subDay(),
            'due_at' => now()->addDays(3),
            'returned_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(LoanTransactionService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Batas pinjam aktif tercapai');

        $service->createLoan([
            'institution_id' => $institutionId,
            'member_id' => $memberId,
            'barcodes' => ['BC-CIR-LIMIT-2'],
            'actor_user_id' => $admin->id,
            'actor_role' => 'admin',
            'staff_branch_id' => $branchId,
        ]);
    }
}

