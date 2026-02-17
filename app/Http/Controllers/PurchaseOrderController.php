<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use App\Http\Requests\AddPurchaseOrderLineRequest;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\AcquisitionsService;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseOrderController extends Controller
{
    use CatalogAccess;

    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $vendorId = (string) $request->query('vendor_id', '');
        $branchId = (string) $request->query('branch_id', '');

        $query = PurchaseOrder::query()->with(['vendor', 'branch'])->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($vendorId !== '') {
            $query->where('vendor_id', (int) $vendorId);
        }
        if ($branchId !== '') {
            $query->where('branch_id', (int) $branchId);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('po_number', 'like', "%{$q}%")
                  ->orWhere('currency', 'like', "%{$q}%");
            });
        }

        $pos = $query->paginate(15)->withQueryString();
        $vendors = Vendor::query()->orderBy('name')->get();
        $branches = Schema::hasTable('branches')
            ? DB::table('branches')->select(['id', 'name', 'code'])->orderBy('name')->get()
            : collect();

        $statusCounts = PurchaseOrder::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('acquisitions.pos.index', [
            'title' => 'Purchase Orders',
            'pos' => $pos,
            'status' => $status,
            'q' => $q,
            'vendorId' => $vendorId,
            'branchId' => $branchId,
            'vendors' => $vendors,
            'branches' => $branches,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create()
    {
        $this->authorize('create', PurchaseOrder::class);

        $vendors = Vendor::query()->orderBy('name')->get();
        $branches = Schema::hasTable('branches')
            ? DB::table('branches')->select(['id', 'name', 'code'])->orderBy('name')->get()
            : collect();

        return view('acquisitions.pos.create', [
            'title' => 'Buat PO',
            'vendors' => $vendors,
            'branches' => $branches,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, AcquisitionsService $service)
    {
        $this->authorize('create', PurchaseOrder::class);

        $po = $service->createPO($request->validated(), $request->user());

        return redirect()
            ->route('acquisitions.pos.show', $po->id)
            ->with('success', 'PO dibuat.');
    }

    public function show(int $id)
    {
        $po = PurchaseOrder::query()
            ->with([
                'vendor',
                'branch',
                'lines',
                'receipts.receiver',
                'receipts.lines.purchaseOrderLine',
            ])
            ->findOrFail($id);
        $this->authorize('view', $po);
        $vendors = Vendor::query()->orderBy('name')->get();
        $budget = app(BudgetService::class)->getBudget((int) now()->format('Y'), $po->branch_id ? (int) $po->branch_id : null);

        return view('acquisitions.pos.show', [
            'title' => 'Detail PO',
            'po' => $po,
            'vendors' => $vendors,
            'budget' => $budget,
        ]);
    }

    public function addLine(int $id, AddPurchaseOrderLineRequest $request, AcquisitionsService $service)
    {
        $po = PurchaseOrder::query()->findOrFail($id);
        $this->authorize('update', $po);
        $service->addPOLine($po, $request->validated(), $request->user());

        return redirect()
            ->route('acquisitions.pos.show', $po->id)
            ->with('success', 'Line ditambahkan.');
    }

    public function order(int $id, AcquisitionsService $service)
    {
        $po = PurchaseOrder::query()->findOrFail($id);
        $this->authorize('update', $po);
        try {
            $service->markPOOrdered($po, request()->user());
        } catch (\Throwable $e) {
            return redirect()
                ->route('acquisitions.pos.show', $po->id)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('acquisitions.pos.show', $po->id)
            ->with('success', 'PO diubah ke ordered.');
    }

    public function cancel(int $id, AcquisitionsService $service)
    {
        $po = PurchaseOrder::query()->findOrFail($id);
        $this->authorize('update', $po);
        $service->cancelPO($po, request()->user());

        return redirect()
            ->route('acquisitions.pos.show', $po->id)
            ->with('success', 'PO dibatalkan.');
    }

    public function receive(int $id, ReceivePurchaseOrderRequest $request, AcquisitionsService $service)
    {
        $po = PurchaseOrder::query()->findOrFail($id);
        $this->authorize('receive', $po);
        $data = $request->validated();
        $receivedAt = !empty($data['received_at']) ? new \DateTime($data['received_at']) : null;

        try {
            $result = $service->receivePO(
                $po,
                $data['lines'],
                $this->currentInstitutionId(),
                $request->user(),
                $data['notes'] ?? null,
                $receivedAt
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('acquisitions.pos.show', $po->id)
                ->with('error', $e->getMessage());
        }

        $redirect = redirect()
            ->route('acquisitions.pos.show', $po->id)
            ->with('success', 'Penerimaan tersimpan.');

        if (!empty($result['budget_warning'])) {
            $redirect->with('warning', $result['budget_warning']);
        }

        return $redirect;
    }
}
