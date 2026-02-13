<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservationService
{
    /**
     * Durasi HOLD (READY) dalam jam.
     */
    public int $holdHours = 48;

    /**
     * LIST untuk halaman reservasi.index (sesuai blade kamu)
     *
     * Return minimal:
     * - items (collection)
     * - memberLinked (bool)
     */
    public function listReservations(
        int $institutionId,
        ?int $memberId = null,
        string $filter = 'all',
        string $q = '',
        string $mode = 'member'
    ): array {
        if (!Schema::hasTable('reservations')) {
            return [
                'items' => collect(),
                'memberLinked' => ($memberId !== null ? ((int)$memberId > 0) : true),
                'error' => 'Tabel reservations tidak ditemukan.',
            ];
        }

        $q = trim((string)$q);
        $filter = (string)$filter;

        // UI filter: all|queued|ready|done (done = fulfilled/cancelled/expired)
        $statusFilter = null;
        if (in_array($filter, ['queued', 'ready'], true)) {
            $statusFilter = [$filter];
        } elseif ($filter === 'done') {
            $statusFilter = ['fulfilled', 'cancelled', 'expired'];
        }

        // Jika member mode tapi belum linked (memberId <= 0)
        $memberLinked = true;
        if ($memberId !== null && (int)$memberId <= 0) {
            $memberLinked = false;
            return [
                'items' => collect(),
                'memberLinked' => false,
            ];
        }

        $query = DB::table('reservations as r')
            ->where('r.institution_id', $institutionId);

        if ($memberId !== null) {
            $query->where('r.member_id', (int)$memberId);
        }

        if ($statusFilter !== null) {
            $query->whereIn('r.status', $statusFilter);
        }

        // Joins aman (hanya kalau tabelnya ada)
        $hasBiblio = Schema::hasTable('biblio');
        $hasMembers = Schema::hasTable('members');

        if ($hasBiblio) {
            $query->leftJoin('biblio as b', 'b.id', '=', 'r.biblio_id');
        }

        if ($hasMembers) {
            $query->leftJoin('members as m', 'm.id', '=', 'r.member_id');
        }

        // Tentukan kolom-kolom members yang benar-benar ada (biar tidak error SQL)
        $hasFullName = $hasMembers && Schema::hasColumn('members', 'full_name');
        $hasName     = $hasMembers && Schema::hasColumn('members', 'name');
        $hasNama     = $hasMembers && Schema::hasColumn('members', 'nama');
        $hasMemberCode = $hasMembers && Schema::hasColumn('members', 'member_code');

        // Expr aman untuk member_name/member_code
        $memberNameExpr = "''";
        if ($hasFullName && $hasName) {
            $memberNameExpr = "COALESCE(m.full_name, m.name, '')";
        } elseif ($hasFullName) {
            $memberNameExpr = "COALESCE(m.full_name, '')";
        } elseif ($hasName) {
            $memberNameExpr = "COALESCE(m.name, '')";
        } elseif ($hasNama) {
            $memberNameExpr = "COALESCE(m.nama, '')";
        }

        $memberCodeExpr = $hasMemberCode ? "COALESCE(m.member_code, '')" : "''";

        // Search (q)
        if ($q !== '') {
            $query->where(function ($w) use ($q, $mode, $hasBiblio, $hasFullName, $hasName, $hasNama, $hasMemberCode) {
                if ($hasBiblio) {
                    $w->orWhere('b.title', 'like', '%' . $q . '%');
                } else {
                    $w->orWhere('r.biblio_id', 'like', '%' . $q . '%');
                }

                // Staff boleh cari member
                if ($mode === 'staff') {
                    if ($hasFullName) $w->orWhere('m.full_name', 'like', '%' . $q . '%');
                    if ($hasName)     $w->orWhere('m.name', 'like', '%' . $q . '%');
                    if ($hasNama)     $w->orWhere('m.nama', 'like', '%' . $q . '%');
                    if ($hasMemberCode) $w->orWhere('m.member_code', 'like', '%' . $q . '%');
                }
            });
        }

        // Samakan field yang view butuhkan: ready_item_id
        $readyItemExpr = 'NULL';
        if (Schema::hasColumn('reservations', 'ready_item_id')) {
            $readyItemExpr = 'r.ready_item_id';
        } elseif (Schema::hasColumn('reservations', 'item_id')) {
            $readyItemExpr = 'r.item_id';
        }

        // biblio_title aman
        $biblioTitleExpr = $hasBiblio ? "COALESCE(b.title, '')" : "''";

        $items = $query
            ->orderByDesc('r.created_at')
            ->select([
                'r.*',
                DB::raw($biblioTitleExpr . " as biblio_title"),
                DB::raw($memberNameExpr . " as member_name"),
                DB::raw($memberCodeExpr . " as member_code"),
                DB::raw($readyItemExpr . " as ready_item_id"),
            ])
            ->get();

        return [
            'items' => $items,
            'memberLinked' => $memberLinked,
        ];
    }

    /* ========================================
     * BARCODE -> ITEM -> BIBLIO (FK SAFE)
     * ======================================== */
    public function resolveBiblioFromBarcode(int $institutionId, string $barcodeOrCode): array
    {
        $code = trim((string)$barcodeOrCode);
        if ($code === '') {
            return ['ok' => false, 'message' => 'Barcode tidak boleh kosong.'];
        }

        if (!Schema::hasTable('items')) {
            return ['ok' => false, 'message' => 'Tabel items tidak ditemukan.'];
        }

        $q = DB::table('items')->where('institution_id', $institutionId);

        $q->where(function ($w) use ($code) {
            if (Schema::hasColumn('items', 'barcode')) {
                $w->orWhere('barcode', $code);
            }
            if (Schema::hasColumn('items', 'inventory_code')) {
                $w->orWhere('inventory_code', $code);
            }
            if (Schema::hasColumn('items', 'accession_number')) {
                $w->orWhere('accession_number', $code);
            }
        });

        $item = $q->orderByDesc('id')->first();

        if (!$item) {
            return ['ok' => false, 'message' => 'Barcode tidak ditemukan di data eksemplar (items).'];
        }

        $biblioId = (int)($item->biblio_id ?? 0);
        if ($biblioId <= 0) {
            return ['ok' => false, 'message' => 'Eksemplar ditemukan, tapi belum terhubung ke data buku (biblio_id kosong).'];
        }

        // Pastikan biblio ada agar insert reservations tidak gagal FK
        if (Schema::hasTable('biblio')) {
            $exists = DB::table('biblio')->where('id', $biblioId)->exists();
            if (!$exists) {
                return ['ok' => false, 'message' => "Buku tidak ditemukan di tabel biblio (biblio_id={$biblioId})."];
            }
        }

        return [
            'ok' => true,
            'item_id' => (int)($item->id ?? 0),
            'biblio_id' => $biblioId,
            'barcode' => $code,
        ];
    }

    public function createReservationByBarcode(
        int $institutionId,
        int $memberId,
        string $barcodeOrCode,
        ?string $notes = null,
        ?int $actorUserId = null
    ): array {
        $res = $this->resolveBiblioFromBarcode($institutionId, $barcodeOrCode);
        if (!($res['ok'] ?? false)) return $res;

        $biblioId = (int)$res['biblio_id'];

        // Insert reservasi berdasarkan biblio_id hasil resolve barcode
        return $this->createReservation($institutionId, $memberId, $biblioId, $notes, $actorUserId);
    }

    /* =========================
     * CREATE
     * ========================= */
    public function createReservation(
        int $institutionId,
        int $memberId,
        int $biblioId,
        ?string $notes = null,
        ?int $actorUserId = null
    ): array {
        if (!Schema::hasTable('reservations')) {
            return ['ok' => false, 'message' => 'Tabel reservations tidak ditemukan.'];
        }
        if (!Schema::hasTable('items')) {
            return ['ok' => false, 'message' => 'Tabel items tidak ditemukan.'];
        }
        if ($biblioId <= 0) {
            return ['ok' => false, 'message' => 'biblio_id tidak valid.'];
        }

        // Safety FK: biblio harus ada
        if (Schema::hasTable('biblio')) {
            $exists = DB::table('biblio')->where('id', $biblioId)->exists();
            if (!$exists) {
                return ['ok' => false, 'message' => 'Buku tidak ditemukan (biblio_id tidak ada).'];
            }
        }

        return DB::transaction(function () use ($institutionId, $memberId, $biblioId, $notes, $actorUserId) {

            // Lock semua reservasi biblio ini agar queue aman
            $base = DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('biblio_id', $biblioId)
                ->lockForUpdate();

            // Cegah duplikat reservasi aktif
            $dup = (clone $base)
                ->where('member_id', $memberId)
                ->whereIn('status', ['queued', 'ready'])
                ->whereNull('fulfilled_at')
                ->exists();

            if ($dup) {
                return ['ok' => false, 'message' => 'Anda sudah memiliki reservasi aktif untuk judul ini.'];
            }

            $maxQueue = (int)((clone $base)->max('queue_no') ?? 0);
            $queueNo = max(1, $maxQueue + 1);

            $payload = [
                'institution_id' => $institutionId,
                'member_id'      => $memberId,
                'biblio_id'      => $biblioId,
                'queue_no'       => $queueNo,
                'status'         => 'queued',
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            // dukung dua schema item
            if (Schema::hasColumn('reservations', 'item_id')) $payload['item_id'] = null;
            if (Schema::hasColumn('reservations', 'ready_item_id')) $payload['ready_item_id'] = null;

            // timestamps hold
            if (Schema::hasColumn('reservations', 'reserved_at')) $payload['reserved_at'] = null;
            if (Schema::hasColumn('reservations', 'ready_at')) $payload['ready_at'] = null;
            if (Schema::hasColumn('reservations', 'expires_at')) $payload['expires_at'] = null;

            if (Schema::hasColumn('reservations', 'fulfilled_at')) $payload['fulfilled_at'] = null;

            if (Schema::hasColumn('reservations', 'notes')) $payload['notes'] = $notes ? trim((string)$notes) : null;
            if (Schema::hasColumn('reservations', 'handled_by')) $payload['handled_by'] = $actorUserId;

            $reservationId = DB::table('reservations')->insertGetId($payload);

            // kalau ada kolom yang default CURRENT_TIMESTAMP, kita paksa null saat queued
            $forceNull = [];
            if (Schema::hasColumn('reservations', 'reserved_at')) $forceNull['reserved_at'] = null;
            if (Schema::hasColumn('reservations', 'ready_at')) $forceNull['ready_at'] = null;
            if (!empty($forceNull)) {
                DB::table('reservations')->where('id', $reservationId)->update($forceNull);
            }

            // coba promote antrean terdepan
            $this->tryPromoteNextForBiblio($institutionId, $biblioId, $actorUserId);

            return [
                'ok' => true,
                'id' => (int)$reservationId,
                'queue_no' => (int)$queueNo,
                'message' => 'Reservasi dibuat dan masuk antrian.',
            ];
        });
    }

    /* =========================
     * PROMOTE (queued -> ready)
     * ========================= */
    public function tryPromoteNextForBiblio(int $institutionId, int $biblioId, ?int $actorUserId = null): void
    {
        if (!Schema::hasTable('reservations') || !Schema::hasTable('items')) return;

        DB::transaction(function () use ($institutionId, $biblioId, $actorUserId) {

            // 1 biblio hanya boleh punya 1 ready aktif
            $hasReady = DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('biblio_id', $biblioId)
                ->where('status', 'ready')
                ->whereNull('fulfilled_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->exists();

            if ($hasReady) return;

            $next = DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('biblio_id', $biblioId)
                ->where('status', 'queued')
                ->whereNull('fulfilled_at')
                ->lockForUpdate()
                ->orderBy('queue_no')
                ->orderBy('id')
                ->first(['id']);

            if (!$next) return;

            $item = DB::table('items')
                ->where('institution_id', $institutionId)
                ->where('biblio_id', $biblioId)
                ->where('status', 'available')
                ->lockForUpdate()
                ->orderBy('id')
                ->first(['id','status']);

            if (!$item) return;

            // available -> reserved
            $okItem = DB::table('items')
                ->where('institution_id', $institutionId)
                ->where('id', (int)$item->id)
                ->where('status', 'available')
                ->update([
                    'status' => 'reserved',
                    'updated_at' => now(),
                ]);

            if ($okItem <= 0) return;

            $now = now();
            $expiresAt = $now->copy()->addHours($this->holdHours);

            $upd = [
                'status' => 'ready',
                'expires_at' => $expiresAt,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('reservations', 'item_id')) $upd['item_id'] = (int)$item->id;
            if (Schema::hasColumn('reservations', 'ready_item_id')) $upd['ready_item_id'] = (int)$item->id;

            if (Schema::hasColumn('reservations', 'reserved_at')) $upd['reserved_at'] = $now;
            if (Schema::hasColumn('reservations', 'ready_at')) $upd['ready_at'] = $now;

            if (Schema::hasColumn('reservations', 'handled_by')) $upd['handled_by'] = $actorUserId;

            DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('id', (int)$next->id)
                ->where('status', 'queued')
                ->update($upd);
        });
    }

    /* =========================
     * CANCEL
     * ========================= */
    public function cancelReservation(int $institutionId, int $reservationId, ?int $actorUserId = null): array
    {
        if (!Schema::hasTable('reservations')) {
            return ['ok' => false, 'message' => 'Tabel reservations tidak ditemukan.'];
        }

        return DB::transaction(function () use ($institutionId, $reservationId, $actorUserId) {

            $r = DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first(['id','status','biblio_id','item_id','ready_item_id','fulfilled_at']);

            if (!$r) return ['ok' => false, 'message' => 'Reservasi tidak ditemukan.'];

            if (!empty($r->fulfilled_at) || (string)$r->status === 'fulfilled') {
                return ['ok' => false, 'message' => 'Reservasi sudah terpenuhi.'];
            }

            $status = (string)$r->status;
            if (!in_array($status, ['queued','ready'], true)) {
                return ['ok' => false, 'message' => "Reservasi tidak bisa dibatalkan (status: {$status})."];
            }

            $biblioId = (int)($r->biblio_id ?? 0);
            $itemId = (int)($r->ready_item_id ?? 0);
            if ($itemId <= 0) $itemId = (int)($r->item_id ?? 0);

            // jika ready, release item reserved->available
            if ($status === 'ready' && $itemId > 0 && Schema::hasTable('items')) {
                $it = DB::table('items')
                    ->where('institution_id', $institutionId)
                    ->where('id', $itemId)
                    ->lockForUpdate()
                    ->first(['id','status']);

                if ($it && (string)$it->status === 'reserved') {
                    DB::table('items')
                        ->where('institution_id', $institutionId)
                        ->where('id', $itemId)
                        ->where('status', 'reserved')
                        ->update([
                            'status' => 'available',
                            'updated_at' => now(),
                        ]);
                }
            }

            $upd = [
                'status' => 'cancelled',
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('reservations', 'item_id')) $upd['item_id'] = null;
            if (Schema::hasColumn('reservations', 'ready_item_id')) $upd['ready_item_id'] = null;

            if (Schema::hasColumn('reservations', 'cancelled_at')) $upd['cancelled_at'] = now();
            if (Schema::hasColumn('reservations', 'cancelled_by')) $upd['cancelled_by'] = $actorUserId;
            if (Schema::hasColumn('reservations', 'handled_by')) $upd['handled_by'] = $actorUserId;

            DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('id', $reservationId)
                ->update($upd);

            if ($biblioId > 0) {
                $this->tryPromoteNextForBiblio($institutionId, $biblioId, $actorUserId);
            }

            return ['ok' => true];
        });
    }

    /* =========================
     * FULFILL (staff)
     * ========================= */
    public function fulfillReservation(int $institutionId, int $reservationId, ?int $actorUserId = null): array
    {
        if (!Schema::hasTable('reservations')) {
            return ['ok' => false, 'message' => 'Tabel reservations tidak ditemukan.'];
        }

        return DB::transaction(function () use ($institutionId, $reservationId, $actorUserId) {

            $r = DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first(['id','status','fulfilled_at']);

            if (!$r) return ['ok' => false, 'message' => 'Reservasi tidak ditemukan.'];

            if (!empty($r->fulfilled_at) || (string)$r->status === 'fulfilled') {
                return ['ok' => true];
            }

            if ((string)$r->status !== 'ready') {
                return ['ok' => false, 'message' => 'Reservasi hanya bisa dipenuhi jika status READY.'];
            }

            $upd = [
                'status' => 'fulfilled',
                'fulfilled_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('reservations', 'handled_by')) {
                $upd['handled_by'] = $actorUserId;
            }

            DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('id', $reservationId)
                ->update($upd);

            return ['ok' => true];
        });
    }
}
