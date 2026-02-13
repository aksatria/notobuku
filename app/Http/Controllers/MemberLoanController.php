<?php

namespace App\Http\Controllers;

use App\Services\MemberLoanService;
use App\Support\MemberContext;
use Illuminate\Http\Request;

class MemberLoanController extends Controller
{
    use MemberContext;

    public function __construct(
        protected MemberLoanService $svc
    ) {
        $this->middleware(['auth', 'role.member']);
    }

    public function index(Request $request)
    {
        $ctx = $this->memberContext($request);

        if (empty($ctx['memberId'])) {
            return redirect()->route('member.dashboard')
                ->with('error', 'Akun belum terhubung ke data member.');
        }

        $filter = $this->svc->normalizeFilter($request->query('filter'));

        $rows = $this->svc->paginateLoans(
            institutionId: $ctx['institutionId'],
            memberId: (int) $ctx['memberId'],
            activeBranchId: $ctx['activeBranchId'],
            filter: $filter
        );

        return view('member.loans.index', [
            'title' => 'Pinjaman Saya',
            'filter' => $filter,
            'rows' => $rows,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $ctx = $this->memberContext($request);

        if (empty($ctx['memberId'])) {
            return redirect()->route('member.dashboard')
                ->with('error', 'Akun belum terhubung ke data member.');
        }

        $res = $this->svc->getLoanDetail(
            institutionId: $ctx['institutionId'],
            memberId: (int) $ctx['memberId'],
            loanId: $id,
            activeBranchId: $ctx['activeBranchId']
        );

        if (!($res['ok'] ?? false)) {
            return redirect()
                ->route('member.pinjaman')
                ->with('error', (string) ($res['message'] ?? 'Tidak ditemukan.'));
        }

        return view('member.loans.show', [
            'title' => 'Detail Pinjaman',
            'loan' => $res['loan'],
            'items' => $res['items'],
            'summary' => $res['summary'] ?? [],
        ]);
    }

    public function extend(Request $request, int $id)
    {
        $ctx = $this->memberContext($request);

        if (empty($ctx['memberId'])) {
            return back()->with('error', 'Akun belum terhubung ke data member.');
        }

        // jika suatu saat ada input tambahan, tinggal tambah validate di sini
        // $request->validate([]);

        $res = $this->svc->extendLoan(
            institutionId: $ctx['institutionId'],
            memberId: (int) $ctx['memberId'],
            loanId: $id,
            activeBranchId: $ctx['activeBranchId']
        );

        if (!($res['ok'] ?? false)) {
            return back()->with('error', (string) ($res['message'] ?? 'Gagal.'));
        }

        return back()->with('success', (string) ($res['message'] ?? 'Berhasil.'));
    }
}
