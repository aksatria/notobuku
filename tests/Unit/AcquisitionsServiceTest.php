<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AcquisitionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcquisitionsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_po_creates_items(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-ACQ',
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

        $user = User::create([
            'name' => 'Staff',
            'email' => 'staff@test.local',
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

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-0001',
            'vendor_id' => $vendor->id,
            'branch_id' => $branchId,
            'status' => 'ordered',
            'currency' => 'IDR',
            'total_amount' => 20000,
            'ordered_at' => now(),
            'created_by_user_id' => $user->id,
        ]);

        $line = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'title' => 'Buku Test',
            'author_text' => 'Penulis',
            'isbn' => '1234567890',
            'quantity' => 2,
            'unit_price' => 10000,
            'line_total' => 20000,
            'status' => 'pending',
            'received_quantity' => 0,
        ]);

        $service = app(AcquisitionsService::class);

        $result = $service->receivePO(
            $po,
            [[
                'line_id' => $line->id,
                'quantity_received' => 2,
            ]],
            $institutionId,
            $user,
            null,
            now()
        );

        $this->assertNotEmpty($result['receipt'] ?? null);
        $this->assertSame(2, Item::query()->count());
    }
}
