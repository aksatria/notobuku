<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Kolom yang boleh diisi mass-assignment.
     * Pastikan branch_id ikut fillable supaya bisa di-set dari Seeder/Tinker/Form admin.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',

        'role',
        'status',

        'institution_id',
        'branch_id',

        'sidebar_collapsed',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Laravel 12 OK
            'institution_id' => 'integer',
            'branch_id' => 'integer',
            'sidebar_collapsed' => 'integer',
        ];
    }

    // ============================
    // Helper Role
    // ============================

    public function isSuperAdmin(): bool
    {
        return ($this->role ?? null) === 'super_admin';
    }

    public function isAdmin(): bool
    {
        // super_admin dianggap admin juga
        return in_array(($this->role ?? null), ['admin', 'super_admin'], true);
    }

    public function isStaff(): bool
    {
        return in_array(($this->role ?? null), ['staff', 'admin', 'super_admin'], true);
    }

    public function isMember(): bool
    {
        return ($this->role ?? null) === 'member';
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    // ============================
    // Cabang aktif (super_admin)
    // ============================

    /**
     * Super Admin boleh "switch cabang" lewat session('active_branch_id').
     * Admin/Staff tidak boleh switch — selalu pakai branch_id milik user.
     *
     * Return:
     * - int|null: cabang aktif yang dipakai untuk scope transaksi
     */
    public function activeBranchId(): ?int
    {
        $base = (int)($this->branch_id ?? 0);
        if ($base <= 0) {
            return null;
        }

        // hanya super_admin boleh override
        if (!$this->canSwitchBranch()) {
            return $base;
        }

        $active = (int)session('active_branch_id', 0);
        if ($active <= 0) {
            return $base;
        }

        // validasi cabang aktif harus ada + aktif + (opsional) 1 institusi
        try {
            $branch = Branch::query()
                ->select(['id', 'institution_id', 'is_active'])
                ->where('id', $active)
                ->first();

            if ($branch && (int)($branch->is_active ?? 0) === 1) {
                $userInstitutionId = (int)($this->institution_id ?? 0);
                if ($userInstitutionId <= 0 || (int)$branch->institution_id === $userInstitutionId) {
                    return (int)$active;
                }
            }
        } catch (\Throwable $e) {
            // kalau ada error (misal tabel belum ada), fallback aman:
            return $base;
        }

        return $base;
    }

    public function canSwitchBranch(): bool
    {
        return $this->isSuperAdmin();
    }

    // ============================
    // Relationships (optional)
    // ============================

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
