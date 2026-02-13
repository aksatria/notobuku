<?php

namespace App\Services;

use App\Models\Budget;

class BudgetService
{
    public function getBudget(int $year, ?int $branchId = null): ?Budget
    {
        return Budget::query()
            ->where('year', $year)
            ->when($branchId !== null, function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            }, function ($q) {
                $q->whereNull('branch_id');
            })
            ->first();
    }

    public function spend(int $year, ?int $branchId, float $amount, array $meta = []): array
    {
        $amount = max(0, (float) $amount);
        if ($amount <= 0) {
            return ['ok' => true, 'budget' => null, 'warning' => null];
        }

        $budget = $this->getBudget($year, $branchId);
        if (!$budget) {
            return [
                'ok' => true,
                'budget' => null,
                'warning' => 'Budget belum tersedia untuk tahun ini.',
            ];
        }

        $allowOver = (bool) config('acquisitions.allow_over_budget', true);
        $nextSpent = (float) $budget->spent + $amount;
        $over = $nextSpent > (float) $budget->amount;

        if ($over && !$allowOver) {
            return [
                'ok' => false,
                'budget' => $budget,
                'warning' => 'Budget tidak mencukupi.',
            ];
        }

        $budget->spent = $nextSpent;
        if (!empty($meta)) {
            $existing = is_array($budget->meta_json) ? $budget->meta_json : [];
            $existing['last_spend'] = [
                'amount' => $amount,
                'meta' => $meta,
                'at' => now()->toDateTimeString(),
            ];
            $budget->meta_json = $existing;
        }
        $budget->save();

        return [
            'ok' => true,
            'budget' => $budget,
            'warning' => $over ? 'Budget terlampaui, namun tetap diproses.' : null,
        ];
    }
}
