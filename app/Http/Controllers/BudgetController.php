<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Budget::class);

        $year = (string) $request->query('year', '');

        $query = Budget::query()->orderByDesc('year');
        if ($year !== '') {
            $query->where('year', (int) $year);
        }

        $budgets = $query->paginate(20)->withQueryString();

        return view('budgets.index', [
            'title' => 'Budget',
            'budgets' => $budgets,
            'year' => $year,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Budget::class);

        $branches = Schema::hasTable('branches')
            ? DB::table('branches')->select(['id', 'name', 'code'])->orderBy('name')->get()
            : collect();

        return view('budgets.create', [
            'title' => 'Tambah Budget',
            'branches' => $branches,
        ]);
    }

    public function store(StoreBudgetRequest $request)
    {
        $this->authorize('create', Budget::class);

        Budget::create($request->validated());

        return redirect()
            ->route('acquisitions.budgets.index')
            ->with('success', 'Budget ditambahkan.');
    }

    public function edit(int $id)
    {
        $this->authorize('update', Budget::class);

        $budget = Budget::query()->findOrFail($id);
        $branches = Schema::hasTable('branches')
            ? DB::table('branches')->select(['id', 'name', 'code'])->orderBy('name')->get()
            : collect();

        return view('budgets.edit', [
            'title' => 'Edit Budget',
            'budget' => $budget,
            'branches' => $branches,
        ]);
    }

    public function update(int $id, UpdateBudgetRequest $request)
    {
        $this->authorize('update', Budget::class);

        $budget = Budget::query()->findOrFail($id);
        $budget->fill($request->validated());
        $budget->save();

        return redirect()
            ->route('acquisitions.budgets.index')
            ->with('success', 'Budget diperbarui.');
    }

    public function destroy(int $id)
    {
        $this->authorize('update', Budget::class);

        $budget = Budget::query()->findOrFail($id);
        $budget->delete();

        return redirect()
            ->route('acquisitions.budgets.index')
            ->with('success', 'Budget dihapus.');
    }
}
