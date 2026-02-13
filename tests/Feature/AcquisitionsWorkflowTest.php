<?php

namespace Tests\Feature;

use App\Models\AcquisitionRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcquisitionsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-ACQ-02',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'PST',
            'name' => 'Pusat',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    public function test_happy_path_workflow(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();

        $staff = User::create([
            'name' => 'Staff',
            'email' => 'staff2@test.local',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);

        $vendor = Vendor::create([
            'name' => 'Vendor Test',
            'normalized_name' => 'vendor test',
        ]);

        $resp = $this->actingAs($staff)->post('/acquisitions/requests', [
            'title' => 'Buku Workflow',
            'author_text' => 'Penulis Workflow',
            'isbn' => '9876543210',
            'priority' => 'normal',
            'branch_id' => $branchId,
            'estimated_price' => 15000,
        ]);
        $resp->assertRedirect();

        $request = AcquisitionRequest::query()->first();
        $this->assertNotNull($request);

        $this->actingAs($staff)->post("/acquisitions/requests/{$request->id}/approve");
        $request->refresh();
        $this->assertSame('approved', $request->status);

        $resp = $this->actingAs($staff)->post("/acquisitions/requests/{$request->id}/convert-to-po", [
            'vendor_id' => $vendor->id,
        ]);
        $resp->assertRedirect();

        $po = PurchaseOrder::query()->first();
        $this->assertNotNull($po);

        $this->actingAs($staff)->post("/acquisitions/pos/{$po->id}/order");
        $po->refresh();
        $this->assertSame('ordered', $po->status);

        $line = PurchaseOrderLine::query()->first();
        $this->assertNotNull($line);

        $resp = $this->actingAs($staff)->post("/acquisitions/pos/{$po->id}/receive", [
            'received_at' => now()->format('Y-m-d'),
            'lines' => [
                ['line_id' => $line->id, 'quantity_received' => 1],
            ],
        ]);
        $resp->assertRedirect();

        $this->assertSame(1, Item::query()->count());
    }
}
