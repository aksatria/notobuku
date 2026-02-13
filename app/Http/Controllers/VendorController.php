<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Vendor::class);

        $q = trim((string) $request->query('q', ''));

        $vendors = Vendor::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('vendors.index', [
            'title' => 'Vendor',
            'vendors' => $vendors,
            'q' => $q,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Vendor::class);

        return view('vendors.create', [
            'title' => 'Tambah Vendor',
        ]);
    }

    public function store(StoreVendorRequest $request, VendorService $service)
    {
        $this->authorize('create', Vendor::class);

        $service->upsert($request->validated());

        return redirect()
            ->route('acquisitions.vendors.index')
            ->with('success', 'Vendor ditambahkan.');
    }

    public function edit(int $id)
    {
        $this->authorize('update', Vendor::class);

        $vendor = Vendor::query()->findOrFail($id);

        return view('vendors.edit', [
            'title' => 'Edit Vendor',
            'vendor' => $vendor,
        ]);
    }

    public function update(int $id, UpdateVendorRequest $request, VendorService $service)
    {
        $this->authorize('update', Vendor::class);

        $vendor = Vendor::query()->findOrFail($id);
        $service->upsert($request->validated(), $vendor);

        return redirect()
            ->route('acquisitions.vendors.index')
            ->with('success', 'Vendor diperbarui.');
    }

    public function destroy(int $id)
    {
        $this->authorize('update', Vendor::class);

        $vendor = Vendor::query()->findOrFail($id);
        $vendor->delete();

        return redirect()
            ->route('acquisitions.vendors.index')
            ->with('success', 'Vendor dihapus.');
    }
}
