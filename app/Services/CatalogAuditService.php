<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Biblio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CatalogAuditService
{
    public function buildEditData(int $institutionId, int $id): array
    {
        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'tags'])
            ->withCount([
                'items',
                'availableItems as available_items_count',
            ])
            ->findOrFail($id);

        $authorsText = $biblio->authors?->pluck('name')->filter()->implode(', ') ?? '';
        $subjectsText = $biblio->subjects?->pluck('term')->filter()->implode('; ') ?? '';
        $tagsText = $biblio->tags?->pluck('name')->filter()->implode(', ') ?? '';

        $marcErrors = collect();
        $marcWarnings = collect();
        try {
            $issues = (new MarcValidationService())->validateForExport($biblio);
            $marcErrors = collect($issues)
                ->filter(fn ($msg) => !str_starts_with((string) $msg, 'WARN:'))
                ->values();
            $marcWarnings = collect($issues)
                ->filter(fn ($msg) => str_starts_with((string) $msg, 'WARN:'))
                ->map(fn ($msg) => trim(substr((string) $msg, 5)))
                ->values();
        } catch (\Throwable $e) {
            \Log::warning('MARC validation preview failed: ' . $e->getMessage());
        }

        $auditRows = $this->auditQuery($id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $auditUsers = User::query()
            ->whereIn('id', $auditRows->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return [
            'biblio' => $biblio,
            'authorsText' => $authorsText,
            'subjectsText' => $subjectsText,
            'tagsText' => $tagsText,
            'attachments' => $biblio->attachments?->sortByDesc('created_at')->values() ?? collect(),
            'canManage' => true,
            'marcErrors' => $marcErrors,
            'marcWarnings' => $marcWarnings,
            'auditRows' => $auditRows,
            'auditUsers' => $auditUsers,
        ];
    }

    public function buildAuditData(int $institutionId, int $id, array $filters): array
    {
        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $query = $this->auditQuery($id)->orderByDesc('created_at');
        $query = $this->applyFilters($query, $filters);

        $audits = $query->paginate(50);
        $auditUsers = User::query()
            ->whereIn('id', collect($audits->items())->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return [
            'biblio' => $biblio,
            'audits' => $audits,
            'auditUsers' => $auditUsers,
            'auditFilters' => [
                'action' => (string) ($filters['action'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'start_date' => (string) ($filters['start_date'] ?? ''),
                'end_date' => (string) ($filters['end_date'] ?? ''),
            ],
        ];
    }

    public function buildAuditCsvData(int $institutionId, int $id, array $filters): array
    {
        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $query = $this->auditQuery($id)->orderByDesc('created_at');
        $query = $this->applyFilters($query, $filters);

        $rows = $query->get();
        $auditUsers = User::query()
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return [
            'rows' => $rows,
            'auditUsers' => $auditUsers,
            'fileName' => 'audit_katalog_' . $biblio->id . '.csv',
        ];
    }

    private function auditQuery(int $biblioId): Builder
    {
        return AuditLog::query()
            ->where(function ($q) use ($biblioId) {
                $q->where('format', 'biblio')
                    ->where('meta->biblio_id', $biblioId);
            })
            ->orWhere(function ($q) use ($biblioId) {
                $q->where('format', 'biblio_attachment')
                    ->where('meta->biblio_id', $biblioId);
            });
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $query->where('action', $action);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $start = trim((string) ($filters['start_date'] ?? ''));
        if ($start !== '') {
            $query->whereDate('created_at', '>=', $start);
        }

        $end = trim((string) ($filters['end_date'] ?? ''));
        if ($end !== '') {
            $query->whereDate('created_at', '<=', $end);
        }

        return $query;
    }
}

