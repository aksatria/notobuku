<?php

namespace App\Services;

use App\Models\AcquisitionRequest;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\Biblio;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AcquisitionsService
{
    public function __construct(
        private MetadataMappingService $mappingService,
        private BudgetService $budgetService
    ) {
    }

    public function createRequest(array $data, ?User $actor = null): AcquisitionRequest
    {
        return DB::transaction(function () use ($data, $actor) {
            $payload = [
                'source' => $data['source'] ?? 'staff_manual',
                'title' => trim((string) ($data['title'] ?? '')),
                'author_text' => $data['author_text'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'notes' => $data['notes'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'requested',
                'branch_id' => $data['branch_id'] ?? null,
                'estimated_price' => $data['estimated_price'] ?? null,
                'requester_user_id' => $data['requester_user_id'] ?? ($actor?->id ?? null),
                'book_request_id' => $data['book_request_id'] ?? null,
            ];

            if (!empty($payload['book_request_id'])) {
                $bookRequest = BookRequest::query()->find($payload['book_request_id']);
                if ($bookRequest) {
                    $payload['source'] = 'member_request';
                    $payload['requester_user_id'] = $bookRequest->user_id;
                    $payload['title'] = $payload['title'] ?: (string) $bookRequest->title;
                    $payload['author_text'] = $payload['author_text'] ?: ($bookRequest->author ?? null);
                    $payload['isbn'] = $payload['isbn'] ?: ($bookRequest->isbn ?? null);
                    $payload['notes'] = $payload['notes'] ?: ($bookRequest->reason ?? null);

                    if (($bookRequest->status ?? null) === 'pending') {
                        $bookRequest->status = 'approved';
                        $bookRequest->processed_by = $actor?->id;
                        $bookRequest->processed_at = now();
                        $bookRequest->save();
                    }
                }
            }

            if ($payload['title'] === '') {
                throw new \InvalidArgumentException('Judul pengadaan wajib diisi.');
            }

            $request = AcquisitionRequest::create($payload);

            $this->writeAudit('acquisitions_request_created', [
                'request_id' => $request->id,
                'source' => $request->source,
                'priority' => $request->priority,
                'branch_id' => $request->branch_id,
            ], $actor);

            return $request;
        });
    }

    public function reviewRequest(AcquisitionRequest $request, ?User $actor = null, array $data = []): AcquisitionRequest
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $request->refresh();

            if (!in_array($request->status, ['requested', 'reviewed'], true)) {
                throw new \RuntimeException('Status request tidak bisa direview.');
            }

            $request->status = 'reviewed';
            $request->reviewed_by_user_id = $actor?->id;
            $request->reviewed_at = now();

            if (array_key_exists('estimated_price', $data)) {
                $request->estimated_price = $data['estimated_price'];
            }
            if (!empty($data['notes'])) {
                $request->notes = $data['notes'];
            }

            $request->save();

            $this->writeAudit('acquisitions_request_reviewed', [
                'request_id' => $request->id,
            ], $actor);

            return $request;
        });
    }

    public function approveRequest(AcquisitionRequest $request, ?User $actor = null, array $data = []): AcquisitionRequest
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $request->refresh();

            if (!in_array($request->status, ['requested', 'reviewed'], true)) {
                throw new \RuntimeException('Status request tidak bisa disetujui.');
            }

            if (array_key_exists('estimated_price', $data)) {
                $request->estimated_price = $data['estimated_price'];
            }

            $request->status = 'approved';
            $request->approved_by_user_id = $actor?->id;
            $request->approved_at = now();
            $request->save();

            $this->writeAudit('acquisitions_request_approved', [
                'request_id' => $request->id,
                'estimated_price' => $request->estimated_price,
            ], $actor);

            return $request;
        });
    }

    public function rejectRequest(AcquisitionRequest $request, ?User $actor = null, ?string $reason = null): AcquisitionRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason) {
            $request->refresh();

            if (in_array($request->status, ['converted_to_po'], true)) {
                throw new \RuntimeException('Request sudah dikonversi ke PO.');
            }

            $request->status = 'rejected';
            $request->rejected_by_user_id = $actor?->id;
            $request->rejected_at = now();
            $request->reject_reason = $reason;
            $request->save();

            $this->writeAudit('acquisitions_request_rejected', [
                'request_id' => $request->id,
                'reason' => $reason,
            ], $actor);

            return $request;
        });
    }

    public function convertRequestToPO(
        AcquisitionRequest $request,
        array $data,
        ?User $actor = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($request, $data, $actor) {
            $request->refresh();

            if ($request->status !== 'approved') {
                throw new \RuntimeException('Request harus berstatus approved sebelum dikonversi.');
            }

            $po = null;
            if (!empty($data['po_id'])) {
                $po = PurchaseOrder::query()->lockForUpdate()->find($data['po_id']);
                if (!$po) {
                    throw new \RuntimeException('PO tidak ditemukan.');
                }
                if ($po->status !== 'draft') {
                    throw new \RuntimeException('PO harus berstatus draft.');
                }
            } else {
                $vendorId = (int) ($data['vendor_id'] ?? 0);
                if ($vendorId <= 0) {
                    throw new \RuntimeException('Vendor wajib dipilih.');
                }

                $po = $this->createPO([
                    'vendor_id' => $vendorId,
                    'branch_id' => $request->branch_id,
                    'currency' => $data['currency'] ?? 'IDR',
                ], $actor);
            }

            $line = $this->addPOLine($po, [
                'biblio_id' => null,
                'title' => $request->title,
                'author_text' => $request->author_text,
                'isbn' => $request->isbn,
                'quantity' => 1,
                'unit_price' => $request->estimated_price ?? 0,
            ], $actor);

            $request->status = 'converted_to_po';
            $request->purchase_order_id = $po->id;
            $request->purchase_order_line_id = $line->id;
            $request->save();

            $this->writeAudit('acquisitions_request_converted', [
                'request_id' => $request->id,
                'po_id' => $po->id,
                'po_line_id' => $line->id,
            ], $actor);

            return $po;
        });
    }

    public function convertRequestsToPO(
        array $requestIds,
        array $data,
        ?User $actor = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($requestIds, $data, $actor) {
            $ids = collect($requestIds)->map(fn ($v) => (int) $v)->filter()->values();
            if ($ids->isEmpty()) {
                throw new \RuntimeException('Tidak ada request yang dipilih.');
            }

            $requests = AcquisitionRequest::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($requests->count() !== $ids->count()) {
                throw new \RuntimeException('Sebagian request tidak ditemukan.');
            }

            $invalid = $requests->first(fn ($r) => $r->status !== 'approved');
            if ($invalid) {
                throw new \RuntimeException('Semua request harus berstatus approved untuk dikonversi.');
            }

            $po = null;
            if (!empty($data['po_id'])) {
                $po = PurchaseOrder::query()->lockForUpdate()->find((int) $data['po_id']);
                if (!$po) {
                    throw new \RuntimeException('PO tidak ditemukan.');
                }
                if ($po->status !== 'draft') {
                    throw new \RuntimeException('PO harus berstatus draft.');
                }
            } else {
                $vendorId = (int) ($data['vendor_id'] ?? 0);
                if ($vendorId <= 0) {
                    throw new \RuntimeException('Vendor wajib dipilih.');
                }

                $po = $this->createPO([
                    'vendor_id' => $vendorId,
                    'branch_id' => $data['branch_id'] ?? null,
                    'currency' => $data['currency'] ?? 'IDR',
                ], $actor);
            }

            $qtyMap = [];
            if (!empty($data['quantities']) && is_array($data['quantities'])) {
                foreach ($data['quantities'] as $rid => $qty) {
                    $rid = (int) $rid;
                    $qtyMap[$rid] = max(1, (int) $qty);
                }
            }

            foreach ($requests as $req) {
                $qty = $qtyMap[$req->id] ?? 1;
                $line = $this->addPOLine($po, [
                    'biblio_id' => null,
                    'title' => $req->title,
                    'author_text' => $req->author_text,
                    'isbn' => $req->isbn,
                    'quantity' => $qty,
                    'unit_price' => $req->estimated_price ?? 0,
                ], $actor);

                $req->status = 'converted_to_po';
                $req->purchase_order_id = $po->id;
                $req->purchase_order_line_id = $line->id;
                $req->save();

                $this->writeAudit('acquisitions_request_converted', [
                    'request_id' => $req->id,
                    'po_id' => $po->id,
                    'po_line_id' => $line->id,
                ], $actor);
            }

            return $po;
        });
    }

    public function updateRequestEstimate(
        AcquisitionRequest $request,
        ?User $actor = null,
        ?float $estimatedPrice = null
    ): AcquisitionRequest {
        return DB::transaction(function () use ($request, $actor, $estimatedPrice) {
            $request->refresh();

            if (in_array($request->status, ['converted_to_po'], true)) {
                throw new \RuntimeException('Request sudah dikonversi ke PO.');
            }

            $request->estimated_price = $estimatedPrice;
            $request->save();

            $this->writeAudit('acquisitions_request_estimate_updated', [
                'request_id' => $request->id,
                'estimated_price' => $estimatedPrice,
            ], $actor);

            return $request;
        });
    }

    public function createPO(array $data, ?User $actor = null): PurchaseOrder
    {
        $vendorId = (int) ($data['vendor_id'] ?? 0);
        if ($vendorId <= 0) {
            throw new \InvalidArgumentException('Vendor wajib diisi.');
        }

        $po = PurchaseOrder::create([
            'po_number' => $this->generatePoNumber(),
            'vendor_id' => $vendorId,
            'branch_id' => $data['branch_id'] ?? null,
            'status' => 'draft',
            'currency' => $data['currency'] ?? 'IDR',
            'total_amount' => 0,
            'created_by_user_id' => $actor?->id,
            'updated_by_user_id' => $actor?->id,
        ]);

        $this->writeAudit('po_created', [
            'po_id' => $po->id,
            'vendor_id' => $po->vendor_id,
            'branch_id' => $po->branch_id,
        ], $actor);

        return $po;
    }

    public function addPOLine(PurchaseOrder $po, array $data, ?User $actor = null): PurchaseOrderLine
    {
        if ($po->status !== 'draft') {
            throw new \RuntimeException('Hanya PO draft yang bisa ditambah line.');
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Judul item PO wajib diisi.');
        }

        $qty = max(1, (int) ($data['quantity'] ?? 1));
        $unitPrice = max(0, (float) ($data['unit_price'] ?? 0));
        $lineTotal = $qty * $unitPrice;

        $line = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'biblio_id' => $data['biblio_id'] ?? null,
            'title' => $title,
            'author_text' => $data['author_text'] ?? null,
            'isbn' => $data['isbn'] ?? null,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'status' => 'pending',
            'received_quantity' => 0,
        ]);

        $this->recalcPoTotal($po);

        $this->writeAudit('po_line_added', [
            'po_id' => $po->id,
            'po_line_id' => $line->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
        ], $actor);

        return $line;
    }

    public function markPOOrdered(PurchaseOrder $po, ?User $actor = null): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new \RuntimeException('PO bukan draft.');
        }

        $lineCount = PurchaseOrderLine::query()
            ->where('purchase_order_id', $po->id)
            ->count();
        if ($lineCount <= 0) {
            throw new \RuntimeException('PO harus memiliki minimal 1 line sebelum di-order.');
        }

        $po->status = 'ordered';
        $po->ordered_at = now();
        $po->updated_by_user_id = $actor?->id;
        $po->save();

        $this->writeAudit('po_ordered', [
            'po_id' => $po->id,
            'total_amount' => $po->total_amount,
        ], $actor);

        return $po;
    }

    public function cancelPO(PurchaseOrder $po, ?User $actor = null): PurchaseOrder
    {
        if (in_array($po->status, ['received', 'cancelled'], true)) {
            throw new \RuntimeException('PO tidak bisa dibatalkan.');
        }

        $po->status = 'cancelled';
        $po->updated_by_user_id = $actor?->id;
        $po->save();

        $this->writeAudit('po_cancelled', [
            'po_id' => $po->id,
        ], $actor);

        return $po;
    }

    public function receivePO(
        PurchaseOrder $po,
        array $lines,
        int $institutionId,
        ?User $actor = null,
        ?string $notes = null,
        ?\DateTimeInterface $receivedAt = null
    ): array {
        return DB::transaction(function () use ($po, $lines, $institutionId, $actor, $notes, $receivedAt) {
            $po->refresh();
            if (!in_array($po->status, ['ordered', 'partially_received'], true)) {
                throw new \RuntimeException('PO belum dalam status ordered.');
            }

            $receivedAt = $receivedAt ? $receivedAt : now();

            $receipt = GoodsReceipt::create([
                'purchase_order_id' => $po->id,
                'received_by_user_id' => $actor?->id,
                'received_at' => $receivedAt,
                'notes' => $notes,
            ]);

            $totalReceiptAmount = 0;
            $branchId = $po->branch_id ?? $actor?->branch_id;

            foreach ($lines as $payload) {
                $lineId = (int) ($payload['line_id'] ?? 0);
                $qty = (int) ($payload['quantity_received'] ?? 0);
                if ($lineId <= 0 || $qty <= 0) {
                    continue;
                }

                /** @var PurchaseOrderLine|null $line */
                $line = PurchaseOrderLine::query()
                    ->where('purchase_order_id', $po->id)
                    ->lockForUpdate()
                    ->find($lineId);

                if (!$line) {
                    throw new \RuntimeException('Line PO tidak ditemukan.');
                }

                $remaining = max(0, (int) $line->quantity - (int) $line->received_quantity);
                if ($qty > $remaining) {
                    throw new \RuntimeException('Qty diterima melebihi sisa PO.');
                }

                $line->received_quantity = (int) $line->received_quantity + $qty;
                if ($line->received_quantity >= $line->quantity) {
                    $line->status = 'received';
                }
                $line->save();

                GoodsReceiptLine::create([
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $line->id,
                    'quantity_received' => $qty,
                ]);

                if (!$line->biblio_id) {
                    $biblio = $this->createMinimalBiblio($line, $institutionId);
                    $line->biblio_id = $biblio->id;
                    $line->save();
                }

                $this->createItemsForLine($line, $qty, $institutionId, $branchId, $receivedAt);

                $totalReceiptAmount += $qty * (float) $line->unit_price;
            }

            $this->recalcPoStatus($po);

            $budgetResult = $this->budgetService->spend(
                (int) now()->format('Y'),
                $branchId ? (int) $branchId : null,
                $totalReceiptAmount,
                [
                    'po_id' => $po->id,
                    'receipt_id' => $receipt->id,
                ]
            );

            if (!($budgetResult['ok'] ?? true)) {
                throw new \RuntimeException($budgetResult['warning'] ?? 'Budget tidak mencukupi.');
            }

            $this->writeAudit('po_received', [
                'po_id' => $po->id,
                'receipt_id' => $receipt->id,
                'received_total' => $totalReceiptAmount,
                'budget_warning' => $budgetResult['warning'] ?? null,
            ], $actor);

            return [
                'receipt' => $receipt,
                'budget_warning' => $budgetResult['warning'] ?? null,
            ];
        });
    }

    private function createMinimalBiblio(PurchaseOrderLine $line, int $institutionId): Biblio
    {
        $title = trim((string) $line->title);
        $authorText = trim((string) ($line->author_text ?? ''));

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => $title,
            'normalized_title' => $this->mappingService->normalize($title),
            'isbn' => $line->isbn,
            'publisher' => null,
            'publish_year' => null,
            'language' => 'id',
            'ai_status' => 'approved',
        ]);

        if ($authorText !== '') {
            $this->mappingService->ensureAuthorityAuthor($authorText);
        }

        $this->mappingService->syncMetadataForBiblio($biblio);

        Book::create([
            'title' => $title,
            'author' => $authorText !== '' ? $authorText : null,
            'isbn' => $line->isbn,
            'publisher' => null,
            'year' => null,
            'subject' => null,
            'call_number' => null,
            'description' => null,
            'cover_path' => null,
        ]);

        return $biblio;
    }

    private function createItemsForLine(PurchaseOrderLine $line, int $qty, int $institutionId, ?int $branchId, $receivedAt): void
    {
        for ($i = 0; $i < $qty; $i++) {
            $barcode = $this->generateBarcode($branchId);
            $accession = $this->generateUnique('ACC', 'accession_number');

            $payload = [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'biblio_id' => $line->biblio_id,
                'barcode' => $barcode,
                'accession_number' => $accession,
                'status' => 'available',
                'acquired_at' => $receivedAt instanceof \DateTimeInterface ? $receivedAt->format('Y-m-d') : now()->format('Y-m-d'),
                'price' => $line->unit_price,
                'source' => 'acquisition',
            ];

            if (Schema::hasColumn('items', 'acquisition_source')) {
                $payload['acquisition_source'] = 'purchase';
            }

            Item::create($payload);
        }
    }

    private function recalcPoTotal(PurchaseOrder $po): void
    {
        $total = PurchaseOrderLine::query()
            ->where('purchase_order_id', $po->id)
            ->sum('line_total');

        $po->total_amount = $total;
        $po->save();
    }

    private function recalcPoStatus(PurchaseOrder $po): void
    {
        $lines = PurchaseOrderLine::query()
            ->where('purchase_order_id', $po->id)
            ->get();

        if ($lines->isEmpty()) {
            $po->status = 'ordered';
            $po->save();
            return;
        }

        $allReceived = $lines->every(fn ($l) => (int) $l->received_quantity >= (int) $l->quantity);
        $anyReceived = $lines->some(fn ($l) => (int) $l->received_quantity > 0);

        if ($allReceived) {
            $po->status = 'received';
            $po->received_at = now();
        } elseif ($anyReceived) {
            $po->status = 'partially_received';
        } else {
            $po->status = 'ordered';
        }

        $po->save();
    }

    private function generatePoNumber(): string
    {
        $date = now()->format('Ymd');
        for ($i = 0; $i < 20; $i++) {
            $code = 'PO-' . $date . '-' . Str::upper(Str::random(6));
            if (!PurchaseOrder::query()->where('po_number', $code)->exists()) return $code;
        }

        return 'PO-' . $date . '-' . Str::upper(Str::random(10));
    }

    private function generateUnique(string $prefix, string $column): string
    {
        $date = now()->format('Ymd');

        for ($tries = 0; $tries < 30; $tries++) {
            $code = $prefix . '-' . $date . '-' . Str::upper(Str::random(6));
            if (!Item::query()->where($column, $code)->exists()) return $code;
        }

        return $prefix . '-' . $date . '-' . Str::upper(Str::random(10));
    }

    private function generateBarcode(?int $branchId): string
    {
        $year = now()->format('Y');
        $branchCode = null;

        if ($branchId && Schema::hasColumn('branches', 'code')) {
            $branchCode = DB::table('branches')->where('id', $branchId)->value('code');
            $branchCode = $branchCode ? strtoupper(trim((string) $branchCode)) : null;
        }

        if ($branchCode) {
            $pattern = $branchCode . '-' . $year . '-%';
            $max = Item::query()
                ->where('barcode', 'like', $pattern)
                ->orderByDesc('barcode')
                ->value('barcode');

            $next = 1;
            if ($max) {
                $parts = explode('-', (string) $max);
                $seq = (int) end($parts);
                $next = $seq + 1;
            }

            $code = sprintf('%s-%s-%04d', $branchCode, $year, $next);
            if (!Item::query()->where('barcode', $code)->exists()) {
                return $code;
            }
        }

        return (string) Str::uuid();
    }

    private function writeAudit(string $action, array $meta, ?User $actor): void
    {
        try {
            AuditLog::create([
                'user_id' => $actor?->id,
                'action' => $action,
                'format' => 'acquisitions',
                'status' => 'success',
                'meta' => $meta,
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }
    }
}
