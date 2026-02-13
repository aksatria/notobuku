<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcquisitionsSeeder extends Seeder
{
    public function run(): void
    {
        $institutionId = DB::table('institutions')
            ->where('code', 'NOTO-01')
            ->value('id');

        if (!$institutionId) {
            $institutionId = DB::table('institutions')->insertGetId([
                'name' => 'Perpustakaan NOTOBUKU',
                'code' => 'NOTO-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $branchId = DB::table('branches')
            ->where('institution_id', $institutionId)
            ->where('code', 'PUSAT')
            ->value('id');

        $vendors = [
            ['name' => 'PT Gramedia', 'normalized_name' => 'pt gramedia'],
            ['name' => 'Mizan Store', 'normalized_name' => 'mizan store'],
        ];

        foreach ($vendors as $v) {
            DB::table('vendors')->updateOrInsert(
                ['normalized_name' => $v['normalized_name']],
                array_merge($v, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        $vendorId = DB::table('vendors')->where('normalized_name', 'pt gramedia')->value('id');

        DB::table('budgets')->updateOrInsert(
            ['year' => (int) now()->format('Y'), 'branch_id' => $branchId],
            [
                'amount' => 5000000,
                'spent' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $request1 = DB::table('acquisitions_requests')->insertGetId([
            'source' => 'staff_manual',
            'title' => 'Pengantar Filsafat',
            'author_text' => 'A. Setiawan',
            'isbn' => '1111111111',
            'notes' => 'Referensi dasar untuk koleksi baru.',
            'priority' => 'normal',
            'status' => 'requested',
            'branch_id' => $branchId,
            'estimated_price' => 75000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('acquisitions_requests')->insert([
            'source' => 'staff_manual',
            'title' => 'Manajemen Perpustakaan Modern',
            'author_text' => 'B. Nugroho',
            'isbn' => '2222222222',
            'notes' => 'Diusulkan staff cataloging.',
            'priority' => 'high',
            'status' => 'reviewed',
            'branch_id' => $branchId,
            'estimated_price' => 95000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $poId = DB::table('purchase_orders')->insertGetId([
            'po_number' => 'PO-DEMO-' . now()->format('Ymd') . '-01',
            'vendor_id' => $vendorId,
            'branch_id' => $branchId,
            'status' => 'draft',
            'currency' => 'IDR',
            'total_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('purchase_order_lines')->insert([
            'purchase_order_id' => $poId,
            'title' => 'Pengantar Filsafat',
            'author_text' => 'A. Setiawan',
            'isbn' => '1111111111',
            'quantity' => 2,
            'unit_price' => 75000,
            'line_total' => 150000,
            'status' => 'pending',
            'received_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('purchase_orders')
            ->where('id', $poId)
            ->update(['total_amount' => 150000, 'updated_at' => now()]);
    }
}
