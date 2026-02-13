<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveAcquisitionRequestRequest;
use App\Http\Requests\BulkConvertAcquisitionRequestsRequest;
use App\Http\Requests\ConvertAcquisitionRequestToPORequest;
use App\Http\Requests\RejectAcquisitionRequestRequest;
use App\Http\Requests\ReviewAcquisitionRequestRequest;
use App\Http\Requests\StoreAcquisitionRequestRequest;
use App\Http\Requests\UpdateAcquisitionEstimateRequest;
use App\Models\AcquisitionRequest;
use App\Models\AuditLog;
use App\Models\BookRequest;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\User;
use App\Services\AcquisitionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcquisitionsRequestController extends Controller
{
    private function currentInstitutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', AcquisitionRequest::class);

        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $priority = (string) $request->query('priority', '');
        $source = (string) $request->query('source', '');
        $branchId = (string) $request->query('branch_id', '');
        $unconvertedOnly = (string) $request->query('unconverted_only', '');

        $query = AcquisitionRequest::query()
            ->with(['branch', 'requester', 'purchaseOrder'])
            ->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($priority !== '') {
            $query->where('priority', $priority);
        }
        if ($source !== '') {
            $query->where('source', $source);
        }
        if ($branchId !== '') {
            $query->where('branch_id', (int) $branchId);
        }
        if ($unconvertedOnly !== '' && $unconvertedOnly !== '0') {
            $query->where('status', '!=', 'converted_to_po');
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                  ->orWhere('author_text', 'like', "%{$q}%")
                  ->orWhere('isbn', 'like', "%{$q}%");
            });
        }

        $requests = $query->paginate(15)->withQueryString();
        $branches = Schema::hasTable('branches')
            ? DB::table('branches')->select(['id', 'name', 'code'])->orderBy('name')->get()
            : collect();
        $vendors = Vendor::query()->orderBy('name')->get();
        $draftPos = PurchaseOrder::query()->where('status', 'draft')->orderByDesc('id')->get();

        $statusCounts = AcquisitionRequest::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('acquisitions.requests.index', [
            'title' => 'Pengadaan - Requests',
            'requests' => $requests,
            'status' => $status,
            'q' => $q,
            'priority' => $priority,
            'source' => $source,
            'branchId' => $branchId,
            'unconvertedOnly' => $unconvertedOnly,
            'branches' => $branches,
            'vendors' => $vendors,
            'draftPos' => $draftPos,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', AcquisitionRequest::class);

        $bookRequestId = (int) $request->query('book_request_id', 0);
        $bookRequest = null;
        if ($bookRequestId > 0) {
            $bookRequest = BookRequest::query()->find($bookRequestId);
        }

        $branches = Schema::hasTable('branches')
            ? DB::table('branches')->select(['id', 'name', 'code'])->orderBy('name')->get()
            : collect();

        $pendingBookRequests = BookRequest::query()
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('acquisitions.requests.create', [
            'title' => 'Buat Request Pengadaan',
            'branches' => $branches,
            'bookRequest' => $bookRequest,
            'pendingBookRequests' => $pendingBookRequests,
        ]);
    }

    public function store(StoreAcquisitionRequestRequest $request, AcquisitionsService $service)
    {
        $this->authorize('create', AcquisitionRequest::class);

        $data = $request->validated();
        $data['source'] = $data['source'] ?? 'staff_manual';

        $acq = $service->createRequest($data, $request->user());

        return redirect()
            ->route('acquisitions.requests.show', $acq->id)
            ->with('success', 'Request pengadaan dibuat.');
    }

    public function show(int $id)
    {
        $request = AcquisitionRequest::query()
            ->with(['branch', 'requester', 'reviewer', 'approver', 'rejector', 'bookRequest', 'purchaseOrder'])
            ->findOrFail($id);
        $this->authorize('view', $request);

        $branches = Schema::hasTable('branches')
            ? DB::table('branches')->select(['id', 'name', 'code'])->orderBy('name')->get()
            : collect();

        $vendors = Vendor::query()->orderBy('name')->get();
        $draftPos = PurchaseOrder::query()->where('status', 'draft')->orderByDesc('id')->get();

        $audits = AuditLog::query()
            ->where('format', 'acquisitions')
            ->where('meta->request_id', $request->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $auditUserIds = $audits->pluck('user_id')->filter()->unique()->values();
        $auditUsers = $auditUserIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $auditUserIds)->get()->keyBy('id');

        return view('acquisitions.requests.show', [
            'title' => 'Detail Request Pengadaan',
            'request' => $request,
            'branches' => $branches,
            'vendors' => $vendors,
            'draftPos' => $draftPos,
            'audits' => $audits,
            'auditUsers' => $auditUsers,
        ]);
    }

    public function review(int $id, ReviewAcquisitionRequestRequest $request, AcquisitionsService $service)
    {
        $acq = AcquisitionRequest::query()->findOrFail($id);
        $this->authorize('update', $acq);
        $service->reviewRequest($acq, $request->user(), $request->validated());

        return redirect()
            ->route('acquisitions.requests.show', $acq->id)
            ->with('success', 'Request telah direview.');
    }

    public function approve(int $id, ApproveAcquisitionRequestRequest $request, AcquisitionsService $service)
    {
        $acq = AcquisitionRequest::query()->findOrFail($id);
        $this->authorize('approve', $acq);
        $service->approveRequest($acq, $request->user(), $request->validated());

        return redirect()
            ->route('acquisitions.requests.show', $acq->id)
            ->with('success', 'Request disetujui.');
    }

    public function reject(int $id, RejectAcquisitionRequestRequest $request, AcquisitionsService $service)
    {
        $acq = AcquisitionRequest::query()->findOrFail($id);
        $this->authorize('reject', $acq);
        $service->rejectRequest($acq, $request->user(), $request->validated()['reject_reason'] ?? null);

        return redirect()
            ->route('acquisitions.requests.show', $acq->id)
            ->with('success', 'Request ditolak.');
    }

    public function convertToPo(int $id, ConvertAcquisitionRequestToPORequest $request, AcquisitionsService $service)
    {
        $acq = AcquisitionRequest::query()->findOrFail($id);
        $this->authorize('update', $acq);
        $po = $service->convertRequestToPO($acq, $request->validated(), $request->user());

        return redirect()
            ->route('acquisitions.pos.show', $po->id)
            ->with('success', 'Request berhasil dikonversi ke PO.');
    }

    public function bulkConvert(BulkConvertAcquisitionRequestsRequest $request, AcquisitionsService $service)
    {
        $this->authorize('create', AcquisitionRequest::class);
        $data = $request->validated();

        try {
            $po = $service->convertRequestsToPO(
                $data['request_ids'],
                $data,
                $request->user()
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('acquisitions.requests.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('acquisitions.pos.show', $po->id)
            ->with('success', 'Bulk convert berhasil.');
    }

    public function updateEstimate(int $id, UpdateAcquisitionEstimateRequest $request, AcquisitionsService $service)
    {
        $acq = AcquisitionRequest::query()->findOrFail($id);
        $this->authorize('update', $acq);

        try {
            $service->updateRequestEstimate($acq, $request->user(), $request->validated()['estimated_price'] ?? null);
        } catch (\Throwable $e) {
            return redirect()
                ->route('acquisitions.requests.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('acquisitions.requests.index')
            ->with('success', 'Estimasi harga diperbarui.');
    }
}
