<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ActiveInstitutionAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservationPolicyController extends Controller
{
    use ActiveInstitutionAccess;

    public function index()
    {
        if (!Schema::hasTable('reservation_policy_rules')) {
            return view('reservasi.rules', ['rules' => collect(), 'enabled' => false]);
        }

        $rules = DB::table('reservation_policy_rules')
            ->where('institution_id', $this->currentInstitutionId())
            ->orderByDesc('is_enabled')
            ->orderByDesc('id')
            ->get();

        return view('reservasi.rules', ['rules' => $rules, 'enabled' => true]);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('reservation_policy_rules')) {
            return back()->with('error', 'Tabel reservation_policy_rules belum tersedia.');
        }

        $v = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'member_type' => ['nullable', 'string', 'max:30'],
            'collection_type' => ['nullable', 'string', 'max:40'],
            'max_active_reservations' => ['required', 'integer', 'min:1', 'max:100'],
            'max_queue_per_biblio' => ['required', 'integer', 'min:1', 'max:500'],
            'hold_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'priority_weight' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
            'is_enabled' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::table('reservation_policy_rules')->insert([
            'institution_id' => $this->currentInstitutionId(),
            'label' => $v['label'],
            'branch_id' => !empty($v['branch_id']) ? (int) $v['branch_id'] : null,
            'member_type' => !empty($v['member_type']) ? strtolower(trim((string) $v['member_type'])) : null,
            'collection_type' => !empty($v['collection_type']) ? strtolower(trim((string) $v['collection_type'])) : null,
            'max_active_reservations' => (int) $v['max_active_reservations'],
            'max_queue_per_biblio' => (int) $v['max_queue_per_biblio'],
            'hold_hours' => (int) $v['hold_hours'],
            'priority_weight' => (int) ($v['priority_weight'] ?? 0),
            'is_enabled' => $request->boolean('is_enabled', true),
            'notes' => $v['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Rule reservasi berhasil ditambahkan.');
    }

    public function toggle(int $id)
    {
        if (!Schema::hasTable('reservation_policy_rules')) {
            return back()->with('error', 'Tabel reservation_policy_rules belum tersedia.');
        }

        $rule = DB::table('reservation_policy_rules')
            ->where('institution_id', $this->currentInstitutionId())
            ->where('id', $id)
            ->first(['id', 'is_enabled']);

        if (!$rule) {
            return back()->with('error', 'Rule tidak ditemukan.');
        }

        DB::table('reservation_policy_rules')->where('id', $id)->update([
            'is_enabled' => !$rule->is_enabled,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Status rule diperbarui.');
    }
}
