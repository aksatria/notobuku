<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use App\Models\AuditLog;
use App\Models\Biblio;
use App\Models\BiblioAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CatalogAttachmentController extends Controller
{
    use CatalogAccess;

    private function canViewAttachment(?string $visibility): bool
    {
        $visibility = $visibility ?: 'staff';

        if ($visibility === 'public') {
            return true;
        }

        if (!auth()->check()) {
            return false;
        }

        if ($this->canManageCatalog()) {
            return true;
        }

        return $visibility === 'member';
    }

    public function store(Request $request, $id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', 'in:public,member,staff'],
            'attachment' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,jpg,jpeg,png,webp,gif,mp3,wav,ogg,mp4,webm,zip,doc,docx,xls,xlsx,ppt,pptx,txt,epub',
            ],
        ]);

        $file = $request->file('attachment');
        $original = $file?->getClientOriginalName() ?? 'attachment';
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo($original, PATHINFO_FILENAME) ?: $original;
        }

        $path = $file->store('attachments', 'public');

        BiblioAttachment::create([
            'biblio_id' => $biblio->id,
            'title' => $title,
            'file_path' => $path,
            'file_name' => $original,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'visibility' => $data['visibility'],
            'created_by' => auth()->id(),
        ]);

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'attachment_add',
                'format' => 'biblio_attachment',
                'status' => 'success',
                'meta' => [
                    'biblio_id' => (int) $biblio->id,
                    'title' => (string) $title,
                    'file_name' => (string) $original,
                    'file_path' => (string) $path,
                    'mime_type' => (string) ($file?->getClientMimeType() ?? ''),
                    'file_size' => (int) ($file?->getSize() ?? 0),
                    'visibility' => (string) ($data['visibility'] ?? 'staff'),
                    'ip' => (string) request()->ip(),
                    'user_agent' => (string) request()->userAgent(),
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }

        return back()->with('success', 'Lampiran berhasil ditambahkan.');
    }

    public function destroy($id, $attachmentId)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $attachment = BiblioAttachment::query()
            ->where('biblio_id', $biblio->id)
            ->findOrFail($attachmentId);

        $auditMeta = [
            'biblio_id' => (int) $biblio->id,
            'attachment_id' => (int) $attachment->id,
            'file_name' => (string) ($attachment->file_name ?? ''),
            'file_path' => (string) ($attachment->file_path ?? ''),
            'mime_type' => (string) ($attachment->mime_type ?? ''),
            'file_size' => (int) ($attachment->file_size ?? 0),
            'visibility' => (string) ($attachment->visibility ?? 'staff'),
            'ip' => (string) request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ];

        try {
            if (!empty($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $attachment->delete();

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'attachment_delete',
                'format' => 'biblio_attachment',
                'status' => 'success',
                'meta' => $auditMeta,
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }

        return back()->with('success', 'Lampiran berhasil dihapus.');
    }

    public function download(Request $request, $id, $attachmentId)
    {
        $isPublic = $request->routeIs('opac.*');

        $biblioQuery = Biblio::query();
        if (!$isPublic) {
            $institutionId = $this->currentInstitutionId();
            $biblioQuery->where('institution_id', $institutionId);
        }

        $biblio = $biblioQuery->findOrFail($id);

        $attachment = BiblioAttachment::query()
            ->where('biblio_id', $biblio->id)
            ->findOrFail($attachmentId);

        if (!$this->canViewAttachment($attachment->visibility)) {
            abort(403);
        }

        $path = $attachment->file_path;
        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $downloadName = $attachment->file_name ?: basename($path);

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'download',
                'format' => 'biblio_attachment',
                'status' => 'success',
                'meta' => [
                    'biblio_id' => (int) $biblio->id,
                    'attachment_id' => (int) $attachment->id,
                    'visibility' => (string) ($attachment->visibility ?? 'staff'),
                    'file_name' => $downloadName,
                    'file_path' => (string) $path,
                    'mime_type' => (string) ($attachment->mime_type ?? ''),
                    'ip' => (string) $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }

        if ((string) $request->query('inline') === '1') {
            $fullPath = Storage::disk('public')->path($path);
            $mime = $attachment->mime_type ?: 'application/pdf';
            return response()->file($fullPath, [
                'Content-Type' => $mime,
            ]);
        }

        return Storage::disk('public')->download($path, $downloadName);
    }
}
