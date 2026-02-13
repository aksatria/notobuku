<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberReservationController extends Controller
{
    public function __construct(private ReservationService $reservationService) {}

    /**
     * Kalau kamu punya route khusus member, pastikan class ini namanya MemberReservationController.
     * (Aman dari bentrok dengan ReservasiController)
     */
    public function index(Request $request)
    {
        // Optional: kalau halaman member punya view sendiri.
        // Kalau kamu nggak pakai controller ini, aman juga biarkan kosong / hapus routenya.
        return redirect()->route('reservasi.index');
    }
}
