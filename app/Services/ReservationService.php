<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReservationService
{
    /**
     * Durasi HOLD (READY) dalam jam.
     */
    public int $holdHours = 48;

    private function policyService(): ReservationPolicyService
    {
        return app(ReservationPolicyService::class);
    }

    private function notificationService(): ReservationNotificationService
    {
        return app(ReservationNotificationService::class);
    }

    /**
     * Expire hold READY yang lewat expires_at.
     * - release item (reserved -> available)
     * - ubah reservasi jadi expired
     * - promote antrean berikutnya
     *
     * @return int jumlah reservasi yang di-expire
     */
    public function expireDueHolds(?int $institutionId = null, int $limit = 200): int
    {
        if (!Schema::hasTable('reservations')) {
            return 0;
        }

        $limit = max(1, $limit);
        if (!Schema::hasColumn('reservations', 'status') || !Schema::hasColumn('reservations', 'expires_at')) {
            return 0;
        }

        $query = DB::table('reservations')
            ->where('status', 'ready')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        if ($institutionId !== null && $institutionId > 0 && Schema::hasColumn('reservations', 'institution_id')) {
            $query->where('institution_id', $institutionId);
        }

        $rows = $query
            ->orderBy('expires_at')
            ->limit($limit)
            ->get(['id', 'institution_id', 'biblio_id', 'item_id', 'ready_item_id']);

        $expiredCount = 0;
        foreach ($rows as $row) {
            $ok = DB::transaction(function () use ($row) {
                $institution = (int) ($row->institution_id ?? 0);
                $reservationId = (int) ($row->id ?? 0);
                $biblioId = (int) ($row->biblio_id ?? 0);

                $locked = DB::table('reservations')
                    ->where('id', $reservationId)
                    ->when($institution > 0, fn ($q) => $q->where('institution_id', $institution))
                    ->lockForUpdate()
                    ->first(['id', 'status', 'item_id', 'ready_item_id', 'biblio_id', 'institution_id', 'expires_at']);

                if (!$locked || (string) ($locked->status ?? '') !== 'ready') {
                    return false;
                }

                $itemId = (int) ($locked->ready_item_id ?? 0);
                if ($itemId <= 0) {
                    $itemId = (int) ($locked->item_id ?? 0);
                }

                if ($itemId > 0 && Schema::hasTable('items')) {
                    $it = DB::table('items')
                        ->where('id', $itemId)
                        ->when($institution > 0 && Schema::hasColumn('items', 'institution_id'), fn ($q) => $q->where('institution_id', $institution))
                        ->lockForUpdate()
                        ->first(['id', 'status']);

                    if ($it && (string) ($it->status ?? '') === 'reserved') {
                        DB::table('items')
                            ->where('id', $itemId)
                            ->when($institution > 0 && Schema::hasColumn('items', 'institution_id'), fn ($q) => $q->where('institution_id', $institution))
                            ->where('status', 'reserved')
                            ->update([
                                'status' => 'available',
                                'updated_at' => now(),
                            ]);
                    }
                }

                $upd = [
                    'status' => 'expired',
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('reservations', 'item_id')) {
                    $upd['item_id'] = null;
                }
                if (Schema::hasColumn('reservations', 'ready_item_id')) {
                    $upd['ready_item_id'] = null;
                }
                if (Schema::hasColumn('reservations', 'cancelled_at')) {
                    $upd['cancelled_at'] = now();
                }

                DB::table('reservations')
                    ->where('id', $reservationId)
                    ->when($institution > 0, fn ($q) => $q->where('institution_id', $institution))
                    ->update($upd);

                $updatedReservation = DB::table('reservations as r')
                    ->leftJoin('biblio as b', 'b.id', '=', 'r.biblio_id')
                    ->leftJoin('members as m', 'm.id', '=', 'r.member_id')
                    ->where('r.id', $reservationId)
                    ->select([
                        'r.id',
                        'r.institution_id',
                        'r.member_id',
                        'r.biblio_id',
                        'r.queue_no',
                        'r.status',
                        'r.expires_at',
                        DB::raw("COALESCE(b.title, '') as biblio_title"),
                        DB::raw("COALESCE(m.full_name, '') as member_name"),
                    ])
                    ->first();

                if ($updatedReservation) {
                    $this->logReservationEvent([
                        'institution_id' => $institution,
                        'reservation_id' => $reservationId,
                        'member_id' => (int) ($updatedReservation->member_id ?? 0),
                        'biblio_id' => $biblioId,
                        'item_id' => $itemId,
                        'event_type' => 'expired',
                        'status_from' => 'ready',
                        'status_to' => 'expired',
                        'queue_no' => (int) ($updatedReservation->queue_no ?? 0),
                    ]);

                    $this->notificationService()->queueForReservationEvent((array) $updatedReservation, 'expired');
                }

                if ($institution > 0 && $biblioId > 0) {
                    $this->tryPromoteNextForBiblio($institution, $biblioId, null);
                }

                return true;
            });

            if ($ok) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

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

        $collectionType = '';
        if (Schema::hasTable('biblio')) {
            $cols = [];
            if (Schema::hasColumn('biblio', 'material_type')) {
                $cols[] = 'material_type';
            }
            if (Schema::hasColumn('biblio', 'media_type')) {
                $cols[] = 'media_type';
            }
            if (!empty($cols)) {
                $b = DB::table('biblio')->where('id', $biblioId)->first($cols);
                $collectionType = strtolower(trim((string) ($b->material_type ?? $b->media_type ?? '')));
            }
        }

        return [
            'ok' => true,
            'item_id' => (int)($item->id ?? 0),
            'biblio_id' => $biblioId,
            'branch_id' => (int) ($item->branch_id ?? 0),
            'collection_type' => $collectionType,
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
        return $this->createReservation(
            $institutionId,
            $memberId,
            $biblioId,
            $notes,
            $actorUserId,
            (int) ($res['branch_id'] ?? 0),
            (string) ($res['collection_type'] ?? '')
        );
    }

    /* =========================
     * CREATE
     * ========================= */
    public function createReservation(
        int $institutionId,
        int $memberId,
        int $biblioId,
        ?string $notes = null,
        ?int $actorUserId = null,
        ?int $branchId = null,
        ?string $collectionType = null
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

        return DB::transaction(function () use ($institutionId, $memberId, $biblioId, $notes, $actorUserId, $branchId, $collectionType) {
            $member = $this->resolveMemberContext($memberId);
            if (!$member) {
                return ['ok' => false, 'message' => 'Data member tidak ditemukan.'];
            }

            $memberStatus = strtolower(trim((string) ($member->status ?? 'active')));
            if ($memberStatus !== 'active') {
                return ['ok' => false, 'message' => 'Akun member sedang nonaktif/suspend, tidak bisa reservasi.'];
            }

            $effectiveCollectionType = strtolower(trim((string) ($collectionType ?? '')));
            if ($effectiveCollectionType === '') {
                $effectiveCollectionType = $this->resolveCollectionTypeFromBiblio($biblioId);
            }
            $effectiveBranchId = (int) ($branchId ?? 0);

            $rule = $this->policyService()->resolveRule($institutionId, [
                'branch_id' => $effectiveBranchId,
                'member_type' => (string) ($member->member_type ?? 'member'),
                'collection_type' => $effectiveCollectionType,
            ]);

            $activeCount = (int) DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('member_id', $memberId)
                ->whereIn('status', ['queued', 'ready'])
                ->count();

            if ($activeCount >= (int) ($rule['max_active_reservations'] ?? 5)) {
                return ['ok' => false, 'message' => 'Kuota reservasi aktif member sudah penuh.'];
            }

            $queueCount = (int) DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('biblio_id', $biblioId)
                ->whereIn('status', ['queued', 'ready'])
                ->count();

            if ($queueCount >= (int) ($rule['max_queue_per_biblio'] ?? 30)) {
                return ['ok' => false, 'message' => 'Antrean reservasi untuk judul ini sudah mencapai batas maksimum.'];
            }

            $priorityScore = $this->policyService()->resolvePriorityScore([
                'member_type' => (string) ($member->member_type ?? 'member'),
            ], $rule);

            $this->holdHours = max(1, (int) ($rule['hold_hours'] ?? $this->holdHours));

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
                'priority_score' => $priorityScore,
                'status'         => 'queued',
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            if (Schema::hasColumn('reservations', 'policy_rule_id')) {
                $payload['policy_rule_id'] = (int) ($rule['id'] ?? 0) ?: null;
            }
            if (Schema::hasColumn('reservations', 'policy_snapshot')) {
                $payload['policy_snapshot'] = json_encode([
                    'rule' => $rule,
                    'branch_id' => $effectiveBranchId > 0 ? $effectiveBranchId : null,
                    'collection_type' => $effectiveCollectionType,
                    'member_type' => (string) ($member->member_type ?? 'member'),
                ], JSON_UNESCAPED_UNICODE);
            }

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
            $this->reorderQueueForBiblio($institutionId, $biblioId);
            $this->tryPromoteNextForBiblio($institutionId, $biblioId, $actorUserId);

            $row = DB::table('reservations as r')
                ->leftJoin('biblio as b', 'b.id', '=', 'r.biblio_id')
                ->leftJoin('members as m', 'm.id', '=', 'r.member_id')
                ->where('r.id', $reservationId)
                ->select([
                    'r.id',
                    'r.institution_id',
                    'r.member_id',
                    'r.biblio_id',
                    'r.queue_no',
                    'r.status',
                    'r.expires_at',
                    DB::raw("COALESCE(b.title, '') as biblio_title"),
                    DB::raw("COALESCE(m.full_name, '') as member_name"),
                ])
                ->first();

            if ($row) {
                $this->logReservationEvent([
                    'institution_id' => $institutionId,
                    'reservation_id' => (int) $reservationId,
                    'member_id' => $memberId,
                    'biblio_id' => $biblioId,
                    'actor_user_id' => $actorUserId,
                    'event_type' => 'created',
                    'status_from' => null,
                    'status_to' => 'queued',
                    'queue_no' => (int) ($row->queue_no ?? $queueNo),
                ]);
                $this->notificationService()->queueForReservationEvent((array) $row, 'created');
            }

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
                ->orderByDesc('priority_score')
                ->orderBy('queue_no')
                ->orderBy('id')
                ->first(['id', 'member_id', 'queue_no', 'created_at', 'priority_score']);

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

            $waitMinutes = null;
            if (!empty($next->created_at)) {
                try {
                    $waitMinutes = now()->diffInMinutes($next->created_at);
                } catch (\Throwable $e) {
                    $waitMinutes = null;
                }
            }

            $row = DB::table('reservations as r')
                ->leftJoin('biblio as b', 'b.id', '=', 'r.biblio_id')
                ->leftJoin('members as m', 'm.id', '=', 'r.member_id')
                ->where('r.id', (int) $next->id)
                ->select([
                    'r.id',
                    'r.institution_id',
                    'r.member_id',
                    'r.biblio_id',
                    'r.queue_no',
                    'r.status',
                    'r.expires_at',
                    DB::raw("COALESCE(b.title, '') as biblio_title"),
                    DB::raw("COALESCE(m.full_name, '') as member_name"),
                ])
                ->first();

            if ($row) {
                $this->logReservationEvent([
                    'institution_id' => $institutionId,
                    'reservation_id' => (int) $next->id,
                    'member_id' => (int) ($next->member_id ?? 0),
                    'biblio_id' => $biblioId,
                    'item_id' => (int) $item->id,
                    'actor_user_id' => $actorUserId,
                    'event_type' => 'ready',
                    'status_from' => 'queued',
                    'status_to' => 'ready',
                    'queue_no' => (int) ($next->queue_no ?? 0),
                    'wait_minutes' => $waitMinutes !== null ? (int) $waitMinutes : null,
                    'meta' => ['priority_score' => (int) ($next->priority_score ?? 0)],
                ]);
                $this->notificationService()->queueForReservationEvent((array) $row, 'ready');
            }
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
                $this->reorderQueueForBiblio($institutionId, $biblioId);
                $this->tryPromoteNextForBiblio($institutionId, $biblioId, $actorUserId);
            }

            $row = DB::table('reservations as r')
                ->leftJoin('biblio as b', 'b.id', '=', 'r.biblio_id')
                ->leftJoin('members as m', 'm.id', '=', 'r.member_id')
                ->where('r.id', $reservationId)
                ->select([
                    'r.id',
                    'r.institution_id',
                    'r.member_id',
                    'r.biblio_id',
                    'r.queue_no',
                    'r.status',
                    'r.expires_at',
                    DB::raw("COALESCE(b.title, '') as biblio_title"),
                    DB::raw("COALESCE(m.full_name, '') as member_name"),
                ])
                ->first();
            if ($row) {
                $this->logReservationEvent([
                    'institution_id' => $institutionId,
                    'reservation_id' => $reservationId,
                    'member_id' => (int) ($row->member_id ?? 0),
                    'biblio_id' => $biblioId,
                    'item_id' => $itemId > 0 ? $itemId : null,
                    'actor_user_id' => $actorUserId,
                    'event_type' => 'cancelled',
                    'status_from' => $status,
                    'status_to' => 'cancelled',
                    'queue_no' => (int) ($row->queue_no ?? 0),
                ]);
                $this->notificationService()->queueForReservationEvent((array) $row, 'cancelled');
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

            $row = DB::table('reservations as r')
                ->leftJoin('biblio as b', 'b.id', '=', 'r.biblio_id')
                ->leftJoin('members as m', 'm.id', '=', 'r.member_id')
                ->where('r.id', $reservationId)
                ->select([
                    'r.id',
                    'r.institution_id',
                    'r.member_id',
                    'r.biblio_id',
                    'r.queue_no',
                    'r.status',
                    'r.expires_at',
                    DB::raw("COALESCE(b.title, '') as biblio_title"),
                    DB::raw("COALESCE(m.full_name, '') as member_name"),
                ])
                ->first();
            if ($row) {
                $this->logReservationEvent([
                    'institution_id' => $institutionId,
                    'reservation_id' => $reservationId,
                    'member_id' => (int) ($row->member_id ?? 0),
                    'biblio_id' => (int) ($row->biblio_id ?? 0),
                    'actor_user_id' => $actorUserId,
                    'event_type' => 'fulfilled',
                    'status_from' => 'ready',
                    'status_to' => 'fulfilled',
                    'queue_no' => (int) ($row->queue_no ?? 0),
                ]);
                $this->notificationService()->queueForReservationEvent((array) $row, 'fulfilled');
            }

            return ['ok' => true];
        });
    }

    public function requeueReservation(int $institutionId, int $reservationId, int $memberId, ?int $actorUserId = null): array
    {
        if (!Schema::hasTable('reservations')) {
            return ['ok' => false, 'message' => 'Tabel reservations tidak ditemukan.'];
        }

        return DB::transaction(function () use ($institutionId, $reservationId, $memberId, $actorUserId) {
            $row = DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('id', $reservationId)
                ->where('member_id', $memberId)
                ->lockForUpdate()
                ->first(['id', 'member_id', 'biblio_id', 'status', 'queue_no']);

            if (!$row) {
                return ['ok' => false, 'message' => 'Reservasi tidak ditemukan.'];
            }

            $status = (string) ($row->status ?? '');
            if (!in_array($status, ['cancelled', 'expired'], true)) {
                return ['ok' => false, 'message' => 'Hanya reservasi batal/kedaluwarsa yang bisa diantre ulang.'];
            }

            $dup = DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('member_id', $memberId)
                ->where('biblio_id', (int) $row->biblio_id)
                ->whereIn('status', ['queued', 'ready'])
                ->exists();
            if ($dup) {
                return ['ok' => false, 'message' => 'Sudah ada reservasi aktif untuk judul ini.'];
            }

            $maxQueue = (int) DB::table('reservations')
                ->where('institution_id', $institutionId)
                ->where('biblio_id', (int) $row->biblio_id)
                ->max('queue_no');

            $upd = [
                'status' => 'queued',
                'queue_no' => max(1, $maxQueue + 1),
                'expires_at' => null,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('reservations', 'item_id')) {
                $upd['item_id'] = null;
            }
            if (Schema::hasColumn('reservations', 'ready_item_id')) {
                $upd['ready_item_id'] = null;
            }
            if (Schema::hasColumn('reservations', 'ready_at')) {
                $upd['ready_at'] = null;
            }
            if (Schema::hasColumn('reservations', 'cancelled_at')) {
                $upd['cancelled_at'] = null;
            }
            if (Schema::hasColumn('reservations', 'cancelled_by')) {
                $upd['cancelled_by'] = null;
            }

            DB::table('reservations')->where('id', (int) $row->id)->update($upd);

            $this->reorderQueueForBiblio($institutionId, (int) $row->biblio_id);
            $this->tryPromoteNextForBiblio($institutionId, (int) $row->biblio_id, $actorUserId);

            $this->logReservationEvent([
                'institution_id' => $institutionId,
                'reservation_id' => (int) $row->id,
                'member_id' => $memberId,
                'biblio_id' => (int) $row->biblio_id,
                'actor_user_id' => $actorUserId,
                'event_type' => 'requeued',
                'status_from' => $status,
                'status_to' => 'queued',
                'queue_no' => max(1, $maxQueue + 1),
            ]);

            return ['ok' => true, 'message' => 'Reservasi berhasil diantre ulang.'];
        });
    }

    public function onItemAvailable(int $institutionId, int $itemId, ?int $actorUserId = null): void
    {
        if (!Schema::hasTable('items') || !Schema::hasTable('reservations')) {
            return;
        }

        $item = DB::table('items')
            ->where('id', $itemId)
            ->when($institutionId > 0 && Schema::hasColumn('items', 'institution_id'), fn ($q) => $q->where('institution_id', $institutionId))
            ->first(['id', 'biblio_id']);

        $biblioId = (int) ($item->biblio_id ?? 0);
        if ($biblioId <= 0) {
            return;
        }

        $this->tryPromoteNextForBiblio($institutionId, $biblioId, $actorUserId);
    }

    private function resolveMemberContext(int $memberId): ?object
    {
        if ($memberId <= 0 || !Schema::hasTable('members')) {
            return null;
        }

        $cols = ['id', 'status'];
        if (Schema::hasColumn('members', 'member_type')) {
            $cols[] = 'member_type';
        }

        return DB::table('members')->where('id', $memberId)->first($cols);
    }

    private function resolveCollectionTypeFromBiblio(int $biblioId): string
    {
        if ($biblioId <= 0 || !Schema::hasTable('biblio')) {
            return '';
        }

        $cols = [];
        if (Schema::hasColumn('biblio', 'material_type')) {
            $cols[] = 'material_type';
        }
        if (Schema::hasColumn('biblio', 'media_type')) {
            $cols[] = 'media_type';
        }
        if (empty($cols)) {
            return '';
        }

        $row = DB::table('biblio')->where('id', $biblioId)->first($cols);
        return strtolower(trim((string) ($row->material_type ?? $row->media_type ?? '')));
    }

    private function reorderQueueForBiblio(int $institutionId, int $biblioId): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        $rows = DB::table('reservations')
            ->where('institution_id', $institutionId)
            ->where('biblio_id', $biblioId)
            ->where('status', 'queued')
            ->orderByDesc('priority_score')
            ->orderBy('queue_no')
            ->orderBy('id')
            ->get(['id']);

        $no = 1;
        foreach ($rows as $row) {
            DB::table('reservations')->where('id', (int) $row->id)->update([
                'queue_no' => $no++,
                'updated_at' => now(),
            ]);
        }
    }

    private function logReservationEvent(array $event): void
    {
        if (!Schema::hasTable('reservation_events')) {
            return;
        }

        DB::table('reservation_events')->insert([
            'institution_id' => (int) ($event['institution_id'] ?? 0),
            'reservation_id' => !empty($event['reservation_id']) ? (int) $event['reservation_id'] : null,
            'member_id' => !empty($event['member_id']) ? (int) $event['member_id'] : null,
            'biblio_id' => !empty($event['biblio_id']) ? (int) $event['biblio_id'] : null,
            'item_id' => !empty($event['item_id']) ? (int) $event['item_id'] : null,
            'actor_user_id' => !empty($event['actor_user_id']) ? (int) $event['actor_user_id'] : null,
            'event_type' => Str::limit((string) ($event['event_type'] ?? 'unknown'), 40, ''),
            'status_from' => !empty($event['status_from']) ? Str::limit((string) $event['status_from'], 20, '') : null,
            'status_to' => !empty($event['status_to']) ? Str::limit((string) $event['status_to'], 20, '') : null,
            'queue_no' => isset($event['queue_no']) ? (int) $event['queue_no'] : null,
            'wait_minutes' => isset($event['wait_minutes']) ? (int) $event['wait_minutes'] : null,
            'meta' => isset($event['meta']) ? json_encode($event['meta'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
