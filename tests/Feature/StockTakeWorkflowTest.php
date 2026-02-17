<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockTakeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Stock',
            'code' => 'INST-STOCK',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Cabang Stock',
            'code' => 'BR-STOCK',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeStaff(int $institutionId, int $branchId): User
    {
        return User::query()->create([
            'name' => 'Staff Stock',
            'email' => 'staff-stock@test.local',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_stock_take_can_start_scan_and_complete_with_missing_count(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $staff = $this->makeStaff($institutionId, $branchId);

        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Buku Opname',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('items')->insert([
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'biblio_id' => $biblioId,
                'barcode' => 'STK-0001',
                'accession_number' => 'ACC-STK-0001',
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'biblio_id' => $biblioId,
                'barcode' => 'STK-0002',
                'accession_number' => 'ACC-STK-0002',
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($staff)->post(route('stock_takes.store'), [
            'name' => 'Opname Mingguan',
            'branch_id' => $branchId,
            'scope_status' => 'all',
        ])->assertRedirect();

        $stockTakeId = (int) DB::table('stock_takes')->value('id');
        $this->assertGreaterThan(0, $stockTakeId);

        $this->actingAs($staff)->post(route('stock_takes.start', $stockTakeId))
            ->assertRedirect(route('stock_takes.show', $stockTakeId));

        $this->actingAs($staff)->post(route('stock_takes.scan', $stockTakeId), [
            'barcode' => 'STK-0001',
        ])->assertRedirect(route('stock_takes.show', $stockTakeId));

        $this->actingAs($staff)->post(route('stock_takes.complete', $stockTakeId))
            ->assertRedirect(route('stock_takes.show', $stockTakeId));

        $this->assertDatabaseHas('stock_takes', [
            'id' => $stockTakeId,
            'status' => 'completed',
            'expected_items_count' => 2,
            'found_items_count' => 1,
            'missing_items_count' => 1,
        ]);
    }
}

