<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Biblio;
use Illuminate\Support\Facades\Storage;

class CatalogMaintenanceService
{
    public function autofix(
        int $institutionId,
        int $id,
        MetadataMappingService $metadataService,
        BiblioAutofixService $autofixService
    ): array {
        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'tags'])
            ->findOrFail($id);

        $changed = $autofixService->autofix($biblio);
        $metadataService->syncMetadataForBiblio($biblio);

        return [
            'id' => (int) $biblio->id,
            'changed' => (bool) $changed,
        ];
    }

    public function destroy(int $institutionId, int $id): array
    {
        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $auditMeta = [
            'biblio_id' => (int) $biblio->id,
            'institution_id' => $institutionId,
            'title' => (string) ($biblio->title ?? ''),
            'publisher' => (string) ($biblio->publisher ?? ''),
            'isbn' => (string) ($biblio->isbn ?? ''),
            'deleted_at' => now()->toIso8601String(),
            'ip' => (string) request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ];

        if ($biblio->items()->exists()) {
            return [
                'ok' => false,
                'error' => 'Tidak bisa menghapus bibliografi yang masih memiliki eksemplar. Hapus/kelola eksemplar dulu.',
            ];
        }

        try {
            if (!empty($biblio->cover_path)) {
                Storage::disk('public')->delete($biblio->cover_path);
            }
        } catch (\Throwable) {
            // ignore
        }

        $biblio->authors()->detach();
        $biblio->subjects()->detach();
        $biblio->tags()->detach();
        $biblio->delete();

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => $auditMeta,
            ]);
        } catch (\Throwable) {
            // ignore
        }

        return [
            'ok' => true,
            'success' => 'Bibliografi berhasil dihapus.',
        ];
    }
}

