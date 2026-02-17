<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CatalogSearchController;
use App\Http\Controllers\CatalogAttachmentController;
use App\Http\Controllers\CatalogImportExportController;
use App\Http\Controllers\CatalogMetadataController;
use App\Http\Controllers\CatalogBulkController;
use App\Http\Controllers\CatalogDetailController;
use App\Http\Controllers\CatalogWriteController;
use App\Http\Controllers\CatalogAuditController;
use App\Http\Controllers\CatalogMaintenanceController;
use App\Http\Controllers\EksemplarController;
use App\Http\Controllers\ReservasiController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\TransaksiDashboardController;
use App\Http\Controllers\NotificationController;

use App\Http\Controllers\AuthorityAuthorController;
use App\Http\Controllers\AuthoritySubjectController;
use App\Http\Controllers\AuthorityPublisherController;
use App\Http\Controllers\TelemetryController;
use App\Http\Controllers\DdcController;
use App\Http\Controllers\AcquisitionsRequestController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\BudgetController;

use App\Http\Controllers\CabangController;
use App\Http\Controllers\RakController;
use App\Http\Controllers\UserPreferenceController;

use App\Http\Controllers\MemberDashboardController;
use App\Http\Controllers\MemberLoanController;

use App\Http\Controllers\MemberReservationController;
use App\Http\Controllers\MemberNotificationController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\MemberSecurityController;
use App\Http\Controllers\ReservationMetricsController;
use App\Http\Controllers\ReservationPolicyController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OperationalReportController;
use App\Http\Controllers\SerialIssueController;
use App\Http\Controllers\OaiPmhController;
use App\Http\Controllers\SruController;
use App\Http\Controllers\InteropMetricsController;
use App\Http\Controllers\CirculationMetricsController;
use App\Http\Controllers\CirculationExceptionController;
use App\Http\Controllers\CirculationPolicyController;
use App\Http\Controllers\OpacSeoController;
use App\Http\Controllers\OpacMetricsController;
use App\Http\Controllers\StockTakeController;
use App\Http\Controllers\CopyCatalogingController;

// ✅ Pustakawan Digital Controller (Utama)
use App\Http\Controllers\PustakawanDigitalController;

// ✅ Beranda role-based
use App\Http\Controllers\BerandaController;

// ✅ Admin AI Monitor
use App\Http\Controllers\Admin\MarcSettingsController;
use App\Http\Controllers\Admin\MarcPolicyApiController;
use App\Http\Controllers\Admin\AdminCollectionController;
use App\Http\Controllers\Admin\SearchSynonymController;
use App\Http\Controllers\Admin\SearchTuningController;
use App\Http\Controllers\Admin\SearchStopWordController;
use App\Http\Controllers\Admin\SearchAnalyticsController;

/*
|--------------------------------------------------------------------------
| Public / Alias Indonesia
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->route('beranda'))->name('home');

// ✅ Beranda sekarang lewat controller (role-based + ambil data service)
Route::get('/beranda', [BerandaController::class, 'index'])->name('beranda');

Route::get('/docs/marc-policy', function () {
    return view('docs.marc-policy');
})->middleware(['auth', 'role.any:super_admin,admin,staff'])->name('docs.marc-policy');

Route::get('/docs', function () {
    return view('docs.index');
})->middleware(['auth'])->name('docs.index');

Route::get('/docs/uat-checklist', function () {
    return view('docs.uat-checklist');
})->middleware(['auth', 'role.any:super_admin,admin,staff'])->name('docs.uat-checklist');

Route::get('/masuk', fn () => redirect()->route('login'))->name('masuk');
Route::get('/daftar', fn () => redirect()->route('register'))->name('daftar');

// ✅ Public OPAC (tanpa login)
Route::get('/sitemap.xml', [OpacSeoController::class, 'sitemap'])
    ->middleware('throttle:opac-public-seo')
    ->name('opac.sitemap');
Route::get('/sitemap-opac-root.xml', [OpacSeoController::class, 'sitemapRoot'])
    ->middleware('throttle:opac-public-seo')
    ->name('opac.sitemap.root');
Route::get('/sitemap-opac-{page}.xml', [OpacSeoController::class, 'sitemapChunk'])
    ->middleware('throttle:opac-public-seo')
    ->whereNumber('page')
    ->name('opac.sitemap.chunk');
Route::get('/robots.txt', [OpacSeoController::class, 'robots'])
    ->middleware('throttle:opac-public-seo')
    ->name('opac.robots');
Route::get('/opac', [CatalogSearchController::class, 'indexPublic'])
    ->middleware(['trace.request', 'opac.conditional', 'track.opac.metrics', 'throttle:opac-public-search'])
    ->name('opac.index');
Route::get('/opac/facets', [CatalogSearchController::class, 'facetsPublic'])
    ->middleware(['trace.request', 'opac.conditional', 'track.opac.metrics', 'throttle:opac-public-search'])
    ->name('opac.facets');
Route::get('/opac/{id}', [CatalogDetailController::class, 'show'])
    ->middleware(['trace.request', 'opac.conditional', 'track.opac.metrics', 'throttle:opac-public-detail'])
    ->whereNumber('id')
    ->name('opac.show');
Route::get('/opac/suggest', [CatalogSearchController::class, 'suggest'])
    ->middleware(['trace.request', 'opac.conditional', 'track.opac.metrics', 'throttle:opac-public-search'])
    ->name('opac.suggest');
Route::get('/opac/{id}/attachments/{attachment}/download', [CatalogAttachmentController::class, 'download'])
    ->middleware('throttle:opac-public-detail')
    ->whereNumber('id')->whereNumber('attachment')
    ->name('opac.attachments.download');
Route::post('/opac/preferences/shelves', [CatalogSearchController::class, 'setShelvesPreference'])
    ->middleware('throttle:opac-public-write')
    ->name('opac.preferences.shelves');
Route::get('/opac/metrics', OpacMetricsController::class)
    ->middleware(['auth', 'role.any:super_admin,admin,staff', 'trace.request'])
    ->name('opac.metrics');
Route::match(['GET', 'POST'], '/oai', [OaiPmhController::class, 'handle'])
    ->middleware(['trace.request', 'throttle:oai-interop'])
    ->name('oai.pmh');
Route::match(['GET', 'POST'], '/sru', [SruController::class, 'handle'])
    ->middleware(['trace.request', 'throttle:sru-interop'])
    ->name('sru.endpoint');

Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('keluar');

/*
|--------------------------------------------------------------------------
| App Entry (after login)
|--------------------------------------------------------------------------
| Semua user MASUK ke sini dulu, lalu diarahkan berdasarkan role
*/
Route::get('/app', function () {
    $user = Auth::user();
    $role = (string) ($user->role ?? 'member');

    return match ($role) {
        'super_admin', 'admin' => redirect()->route('admin.dashboard'),
        'staff' => redirect()->route('staff.dashboard'),
        default => redirect()->route('member.dashboard'),
    };
})->middleware('auth')->name('app');

/*
|--------------------------------------------------------------------------
| Dashboards (Admin / Staff)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::post('/preferences/katalog-ui', [UserPreferenceController::class, 'setKatalogUi'])
        ->name('preferences.katalog_ui.set');

    Route::get('/admin', [AdminDashboardController::class, 'index'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.dashboard');

    Route::get('/admin/koleksi', [AdminCollectionController::class, 'index'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.koleksi');
    Route::get('/admin/koleksi/search', [AdminCollectionController::class, 'search'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.koleksi.search');

    Route::get('/admin/search-synonyms', [SearchSynonymController::class, 'index'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms');
    Route::post('/admin/search-synonyms', [SearchSynonymController::class, 'store'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.store');
    Route::post('/admin/search-synonyms/sync', [SearchSynonymController::class, 'syncAuto'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.sync');
    Route::post('/admin/search-synonyms/import', [SearchSynonymController::class, 'importCsv'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.import');
    Route::post('/admin/search-synonyms/preview', [SearchSynonymController::class, 'previewImport'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.preview');
    Route::post('/admin/search-synonyms/confirm', [SearchSynonymController::class, 'confirmImport'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.confirm');
    Route::post('/admin/search-synonyms/cancel', [SearchSynonymController::class, 'cancelPreview'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.cancel');
    Route::get('/admin/search-synonyms/template', [SearchSynonymController::class, 'downloadTemplate'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.template');
    Route::get('/admin/search-synonyms/errors-csv', [SearchSynonymController::class, 'downloadErrorCsv'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.errors');
    Route::get('/admin/search-synonyms/dups-csv', [SearchSynonymController::class, 'downloadDuplicateCsv'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.dups');
    Route::get('/admin/search-synonyms/preview-csv', [SearchSynonymController::class, 'downloadPreviewCsv'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.preview_csv');
    Route::delete('/admin/search-synonyms/{id}', [SearchSynonymController::class, 'destroy'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.delete');
    Route::post('/admin/search-synonyms/zero-result/{id}/resolve', [SearchSynonymController::class, 'resolveZeroResult'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.zero_result.resolve');

    Route::get('/admin/search-tuning', [SearchTuningController::class, 'index'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_tuning');
    Route::post('/admin/search-tuning', [SearchTuningController::class, 'update'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_tuning.update');
    Route::post('/admin/search-tuning/reset', [SearchTuningController::class, 'reset'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_tuning.reset');
    Route::post('/admin/search-tuning/preset', [SearchTuningController::class, 'applyPreset'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_tuning.preset');

    Route::get('/admin/search-stopwords', [SearchStopWordController::class, 'index'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_stopwords');
    Route::post('/admin/search-stopwords', [SearchStopWordController::class, 'store'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_stopwords.store');
    Route::delete('/admin/search-stopwords/{id}', [SearchStopWordController::class, 'destroy'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_stopwords.delete');

    Route::get('/admin/search-analytics', [SearchAnalyticsController::class, 'index'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_analytics');

    Route::post('/admin/search-synonyms/{id}/approve', [SearchSynonymController::class, 'approve'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.approve');
    Route::post('/admin/search-synonyms/{id}/reject', [SearchSynonymController::class, 'reject'])
        ->middleware('role.any:super_admin,admin')
        ->name('admin.search_synonyms.reject');

    Route::get('/staff', function () {
        return view('placeholders.dashboard', [
            'title' => 'Dashboard Staff',
            'subtitle' => 'Kelola layanan perpustakaan',
            'tone' => 'green',
            'user' => Auth::user(),
        ]);
    })->middleware('role.any:staff')->name('staff.dashboard');
});

/*
|--------------------------------------------------------------------------
| MEMBER AREA
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.member'])
    ->prefix('member')
    ->name('member.')
    ->group(function () {

        Route::get('/', fn () => redirect()->route('member.dashboard'))->name('home');

        // Dashboard
        Route::get('/dashboard', [MemberDashboardController::class, 'index'])->name('dashboard');

        // Pinjaman
        Route::prefix('pinjaman')->group(function () {
            Route::get('/', [MemberLoanController::class, 'index'])->name('pinjaman');

            Route::get('/{id}', [MemberLoanController::class, 'show'])
                ->whereNumber('id')
                ->name('pinjaman.detail');

            Route::post('/{id}/perpanjang', [MemberLoanController::class, 'extend'])
                ->whereNumber('id')
                ->name('pinjaman.extend');
        });

        // Reservasi (member)
        Route::get('/reservasi', [MemberReservationController::class, 'index'])->name('reservasi');

        Route::post('/reservasi/{id}/batalkan', [MemberReservationController::class, 'cancel'])
            ->whereNumber('id')
            ->name('reservasi.cancel');
        Route::post('/reservasi/{id}/requeue', [MemberReservationController::class, 'requeue'])
            ->whereNumber('id')
            ->name('reservasi.requeue');
        Route::get('/reservasi/status', [MemberReservationController::class, 'status'])
            ->name('reservasi.status');

        // Notifikasi (member)
        Route::get('/notifikasi', [MemberNotificationController::class, 'index'])->name('notifikasi');

        Route::post('/notifikasi/{id}/baca', [MemberNotificationController::class, 'markRead'])
            ->whereNumber('id')
            ->name('notifikasi.read');

        Route::post('/notifikasi/baca-semua', [MemberNotificationController::class, 'markAllRead'])
            ->name('notifikasi.read_all');

        // Profil & Keamanan
        Route::get('/profil', [MemberProfileController::class, 'edit'])->name('profil');

        Route::post('/profil', [MemberProfileController::class, 'update'])->name('profil.update');

        Route::get('/keamanan', [MemberSecurityController::class, 'edit'])->name('security');

        Route::post('/keamanan/password', [MemberSecurityController::class, 'updatePassword'])
            ->name('security.password');
        Route::post('/keamanan/freeze', [MemberSecurityController::class, 'freezeAccount'])
            ->name('security.freeze');
        Route::post('/keamanan/unfreeze', [MemberSecurityController::class, 'unfreezeAccount'])
            ->name('security.unfreeze');
        Route::post('/keamanan/reset-kredensial', [MemberSecurityController::class, 'sendResetCredentialLink'])
            ->name('security.reset_credential');

        // ✅ PUSTAKAWAN DIGITAL ROUTES (MODE BEBAS/AHLI)
        Route::prefix('pustakawan-digital')
            ->name('pustakawan.')
            ->group(function () {
                
                // Main interface - Expert Mode
                Route::get('/', [PustakawanDigitalController::class, 'index'])
                    ->name('digital');
                
                // Handle user questions - Freedom Mode
                Route::post('/ask', [PustakawanDigitalController::class, 'handleQuestion'])
                    ->middleware('throttle:20,1')
                    ->name('ask');
                
                // Conversation management
                Route::post('/conversation/new', [PustakawanDigitalController::class, 'startNewConversation'])
                    ->name('conversation.new');
                
                Route::delete('/conversation/{conversationId}', [PustakawanDigitalController::class, 'deleteConversation'])
                    ->whereNumber('conversationId')
                    ->name('conversation.delete');
                
                Route::get('/conversation/history', [PustakawanDigitalController::class, 'getConversationHistory'])
                    ->name('conversation.history');
                
                Route::get('/conversation/{conversationId}/messages', [PustakawanDigitalController::class, 'getConversationMessages'])
                    ->whereNumber('conversationId')
                    ->name('conversation.messages');
                
                // Book requests with expert recommendations
                Route::post('/request-book', [PustakawanDigitalController::class, 'requestBook'])
                    ->name('request.book');
                
                // AI status and testing
                Route::get('/ai-status', [PustakawanDigitalController::class, 'checkAiStatus'])
                    ->name('ai.status');
                
                Route::get('/test-expert', [PustakawanDigitalController::class, 'testMockResponses'])
                    ->name('test.expert');
                
                // Expert knowledge base
                Route::get('/expert-knowledge', [PustakawanDigitalController::class, 'getExpertKnowledge'])
                    ->name('expert.knowledge');
                
                Route::get('/literature-tips', function() {
                    return response()->json([
                        'tips' => [
                            'Baca minimal 1 buku klasik per bulan',
                            'Eksplor minimal 3 genre berbeda per tahun',
                            'Diskusikan buku dengan komunitas pembaca',
                            'Buat reading journal untuk refleksi',
                            'Coba baca ulang buku favorit setelah 5 tahun',
                        ],
                        'expert_mode' => true,
                        'freedom_level' => 'high',
                    ]);
                })->name('literature.tips');
                
                // Categories with expert insights
                Route::get('/categories', [PustakawanDigitalController::class, 'getPopularCategories'])
                    ->name('categories');
                
                // Quick access to expert features
                Route::get('/quick-expert', function() {
                    return response()->json([
                        'expert_features' => [
                            'unlimited_recommendations' => true,
                            'global_literature_knowledge' => true,
                            'historical_literary_analysis' => true,
                            'author_expertise_profiles' => true,
                            'genre_development_timelines' => true,
                            'comparative_literary_studies' => true,
                        ],
                        'status' => 'expert_mode_active',
                        'freedom' => 'unrestricted',
                        'ai_mode' => config('services.ai_debug.force_mock_responses') ? 'expert_mock' : 'ollama_ai',
                    ]);
                })->name('quick.expert');
            });

    });

/*
|--------------------------------------------------------------------------
| SWITCH CABANG (UX) - KHUSUS SUPER ADMIN
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.any:super_admin'])->group(function () {

    Route::get('/admin/switch-cabang', [UserPreferenceController::class, 'switchBranchPage'])
        ->name('admin.switch_cabang');

    Route::post('/preferences/active-branch', [UserPreferenceController::class, 'setActiveBranch'])
        ->name('preferences.active_branch.set');

    Route::post('/preferences/active-branch/reset', [UserPreferenceController::class, 'resetActiveBranch'])
        ->name('preferences.active_branch.reset');
});

/*
|--------------------------------------------------------------------------
| KATALOG
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    Route::get('/katalog', [CatalogSearchController::class, 'index'])->name('katalog.index');
    Route::get('/katalog/facets', [CatalogSearchController::class, 'facets'])->name('katalog.facets');
    Route::get('/katalog/{id}', [CatalogDetailController::class, 'show'])->whereNumber('id')->name('katalog.show');
    Route::get('/katalog/suggest', [CatalogSearchController::class, 'suggest'])->name('katalog.suggest');
    Route::get('/katalog/{id}/attachments/{attachment}/download', [CatalogAttachmentController::class, 'download'])
        ->whereNumber('id')->whereNumber('attachment')
        ->name('katalog.attachments.download');

    Route::middleware(['role.any:super_admin,admin,staff'])->group(function () {

        Route::get('/katalog/export', [CatalogImportExportController::class, 'export'])->name('katalog.export');
        Route::post('/katalog/import', [CatalogImportExportController::class, 'import'])->name('katalog.import');

        Route::get('/authority/authors', [AuthorityAuthorController::class, 'index'])->name('authority.authors');
        Route::get('/authority/subjects', [AuthoritySubjectController::class, 'index'])->name('authority.subjects');
        Route::get('/authority/publishers', [AuthorityPublisherController::class, 'index'])->name('authority.publishers');
        Route::post('/telemetry/autocomplete', [TelemetryController::class, 'autocomplete'])->name('telemetry.autocomplete');
        Route::get('/ddc/search', [DdcController::class, 'search'])->name('ddc.search');

        Route::get('/katalog/tambah', [CatalogWriteController::class, 'create'])->name('katalog.create');
    Route::post('/katalog', [CatalogWriteController::class, 'store'])->name('katalog.store');
        Route::get('/katalog/isbn-lookup', [CatalogMetadataController::class, 'isbnLookup'])->name('katalog.isbnLookup');
        Route::post('/katalog/validate-metadata', [CatalogMetadataController::class, 'validateMetadata'])->name('katalog.validateMetadata');
        Route::post('/katalog/bulk-update', [CatalogBulkController::class, 'bulkUpdate'])->name('katalog.bulkUpdate');
        Route::post('/katalog/bulk-preview', [CatalogBulkController::class, 'bulkPreview'])->name('katalog.bulkPreview');
        Route::post('/katalog/bulk-undo', [CatalogBulkController::class, 'bulkUndo'])->name('katalog.bulkUndo');

        Route::get('/katalog/{id}/edit', [CatalogAuditController::class, 'edit'])->whereNumber('id')->name('katalog.edit');
        Route::post('/katalog/{id}/autofix', [CatalogMaintenanceController::class, 'autofix'])->whereNumber('id')->name('katalog.autofix');
        Route::put('/katalog/{id}', [CatalogWriteController::class, 'update'])->whereNumber('id')->name('katalog.update');
        Route::get('/katalog/{id}/audit', [CatalogAuditController::class, 'audit'])->whereNumber('id')->name('katalog.audit');
        Route::get('/katalog/{id}/audit.csv', [CatalogAuditController::class, 'auditCsv'])->whereNumber('id')->name('katalog.audit.csv');
        Route::delete('/katalog/{id}', [CatalogMaintenanceController::class, 'destroy'])->whereNumber('id')->name('katalog.destroy');
        Route::post('/katalog/{id}/attachments', [CatalogAttachmentController::class, 'store'])
            ->whereNumber('id')->name('katalog.attachments.store');
        Route::delete('/katalog/{id}/attachments/{attachment}', [CatalogAttachmentController::class, 'destroy'])
            ->whereNumber('id')->whereNumber('attachment')
            ->name('katalog.attachments.delete');

        /*
        |--------------------------------------------------------------------------
        | EKSEMPLAR (Items)
        |--------------------------------------------------------------------------
        */
        Route::get('/katalog/{id}/eksemplar', [EksemplarController::class, 'index'])
            ->whereNumber('id')->name('eksemplar.index');

        Route::get('/katalog/{id}/eksemplar/tambah', [EksemplarController::class, 'create'])
            ->whereNumber('id')->name('eksemplar.create');

        Route::post('/katalog/{id}/eksemplar', [EksemplarController::class, 'store'])
            ->whereNumber('id')->name('eksemplar.store');

        Route::get('/katalog/{id}/eksemplar/{item}/edit', [EksemplarController::class, 'edit'])
            ->whereNumber('id')->whereNumber('item')
            ->name('eksemplar.edit');

        Route::put('/katalog/{id}/eksemplar/{item}', [EksemplarController::class, 'update'])
            ->whereNumber('id')->whereNumber('item')
            ->name('eksemplar.update');

        Route::delete('/katalog/{id}/eksemplar/{item}', [EksemplarController::class, 'destroy'])
            ->whereNumber('id')->whereNumber('item')
            ->name('eksemplar.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| MASTER (CABANG)
|--------------------------------------------------------------------------
| NOTE: jangan pakai Route::resource di sini karena bisa mengubah nama route.
*/
Route::middleware(['auth', 'role.any:super_admin,admin,staff'])->group(function () {
    Route::get('/master/cabang', [CabangController::class, 'index'])->name('cabang.index');
    Route::get('/master/cabang/tambah', [CabangController::class, 'create'])->name('cabang.create');
    Route::post('/master/cabang', [CabangController::class, 'store'])->name('cabang.store');

    Route::get('/master/cabang/{id}/edit', [CabangController::class, 'edit'])->whereNumber('id')->name('cabang.edit');
    Route::put('/master/cabang/{id}', [CabangController::class, 'update'])->whereNumber('id')->name('cabang.update');
    Route::delete('/master/cabang/{id}', [CabangController::class, 'destroy'])->whereNumber('id')->name('cabang.destroy');

    Route::post('/master/cabang/{id}/toggle', [CabangController::class, 'toggleActive'])->whereNumber('id')->name('cabang.toggle');
});

/*
|--------------------------------------------------------------------------
| ANGGOTA
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.any:super_admin,admin,staff'])->group(function () {
    Route::get('/anggota', [MemberController::class, 'index'])->name('anggota.index');
    Route::get('/anggota/tambah', [MemberController::class, 'create'])->name('anggota.create');
    Route::post('/anggota', [MemberController::class, 'store'])->name('anggota.store');
    Route::get('/anggota/template/csv', [MemberController::class, 'downloadTemplate'])->name('anggota.template.csv');
    Route::post('/anggota/import/csv', [MemberController::class, 'importCsv'])
        ->middleware('throttle:10,1')
        ->name('anggota.import.csv');
    Route::post('/anggota/import/csv/preview', [MemberController::class, 'previewImportCsv'])
        ->middleware('throttle:10,1')
        ->name('anggota.import.preview');
    Route::post('/anggota/import/csv/confirm', [MemberController::class, 'confirmImportCsv'])
        ->middleware('throttle:20,1')
        ->name('anggota.import.confirm');
    Route::post('/anggota/import/csv/cancel', [MemberController::class, 'cancelImportPreview'])
        ->middleware('throttle:30,1')
        ->name('anggota.import.cancel');
    Route::get('/anggota/import/csv/errors', [MemberController::class, 'downloadImportErrorCsv'])->name('anggota.import.errors');
    Route::get('/anggota/import/csv/summary', [MemberController::class, 'downloadImportSummaryCsv'])->name('anggota.import.summary');
    Route::get('/anggota/import/csv/history', [MemberController::class, 'downloadImportHistoryCsv'])->name('anggota.import.history');
    Route::get('/anggota/import/xlsx/history', [MemberController::class, 'downloadImportHistoryXlsx'])->name('anggota.import.history.xlsx');
    Route::get('/anggota/import/metrics', [MemberController::class, 'importMetrics'])
        ->middleware('throttle:60,1')
        ->name('anggota.import.metrics');
    Route::get('/anggota/import/metrics/chart', [MemberController::class, 'importMetricsChart'])
        ->middleware('throttle:120,1')
        ->name('anggota.import.metrics.chart');
    Route::get('/anggota/metrics/kpi', [MemberController::class, 'kpiMetrics'])
        ->middleware('throttle:60,1')
        ->name('anggota.metrics.kpi');
    Route::post('/anggota/import/csv/undo', [MemberController::class, 'undoImportBatch'])
        ->middleware('throttle:10,1')
        ->name('anggota.import.undo');
    Route::get('/anggota/{id}', [MemberController::class, 'show'])->whereNumber('id')->name('anggota.show');
    Route::get('/anggota/{id}/edit', [MemberController::class, 'edit'])->whereNumber('id')->name('anggota.edit');
    Route::put('/anggota/{id}', [MemberController::class, 'update'])->whereNumber('id')->name('anggota.update');
});

/*
|--------------------------------------------------------------------------
| LAPORAN OPERASIONAL
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.any:super_admin,admin,staff'])->group(function () {
    Route::get('/laporan', [OperationalReportController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/export/{type}', [OperationalReportController::class, 'export'])
        ->whereIn('type', ['sirkulasi', 'overdue', 'denda', 'pengadaan', 'anggota', 'serial', 'sirkulasi_audit'])
        ->name('laporan.export');
    Route::get('/laporan/export-xlsx/{type}', [OperationalReportController::class, 'exportXlsx'])
        ->whereIn('type', ['sirkulasi', 'overdue', 'denda', 'pengadaan', 'anggota', 'serial', 'sirkulasi_audit'])
        ->name('laporan.export_xlsx');
});

/*
|--------------------------------------------------------------------------
| SERIAL ISSUES CONTROL
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.any:super_admin,admin,staff'])->group(function () {
    Route::get('/serial-issues', [SerialIssueController::class, 'index'])->name('serial_issues.index');
    Route::post('/serial-issues', [SerialIssueController::class, 'store'])->name('serial_issues.store');
    Route::get('/serial-issues/export/csv', [SerialIssueController::class, 'exportCsv'])->name('serial_issues.export.csv');
    Route::get('/serial-issues/export/xlsx', [SerialIssueController::class, 'exportXlsx'])->name('serial_issues.export.xlsx');
    Route::post('/serial-issues/{id}/receive', [SerialIssueController::class, 'receive'])
        ->whereNumber('id')
        ->name('serial_issues.receive');
    Route::post('/serial-issues/{id}/missing', [SerialIssueController::class, 'markMissing'])
        ->whereNumber('id')
        ->name('serial_issues.missing');
    Route::post('/serial-issues/{id}/claim', [SerialIssueController::class, 'claim'])
        ->whereNumber('id')
        ->name('serial_issues.claim');
});

/*
|--------------------------------------------------------------------------
| STOCK TAKE / OPNAME
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.any:super_admin,admin,staff'])->group(function () {
    Route::get('/stock-takes', [StockTakeController::class, 'index'])->name('stock_takes.index');
    Route::post('/stock-takes', [StockTakeController::class, 'store'])->name('stock_takes.store');
    Route::get('/stock-takes/{id}', [StockTakeController::class, 'show'])->whereNumber('id')->name('stock_takes.show');
    Route::post('/stock-takes/{id}/start', [StockTakeController::class, 'start'])->whereNumber('id')->name('stock_takes.start');
    Route::post('/stock-takes/{id}/scan', [StockTakeController::class, 'scan'])->whereNumber('id')->name('stock_takes.scan');
    Route::post('/stock-takes/{id}/complete', [StockTakeController::class, 'complete'])->whereNumber('id')->name('stock_takes.complete');
    Route::get('/stock-takes/{id}/export/csv', [StockTakeController::class, 'exportCsv'])->whereNumber('id')->name('stock_takes.export.csv');
});

/*
|--------------------------------------------------------------------------
| COPY CATALOGING CLIENT (SRU / Z39.50 Gateway / P2P)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.any:super_admin,admin,staff'])->group(function () {
    Route::get('/copy-cataloging', [CopyCatalogingController::class, 'index'])->name('copy_cataloging.index');
    Route::post('/copy-cataloging/sources', [CopyCatalogingController::class, 'storeSource'])->name('copy_cataloging.sources.store');
    Route::post('/copy-cataloging/import', [CopyCatalogingController::class, 'import'])->name('copy_cataloging.import');
});

/*
|--------------------------------------------------------------------------
| MASTER (RAK)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.any:super_admin,admin,staff'])->group(function () {
    Route::get('/master/rak', [RakController::class, 'index'])->name('rak.index');
    Route::get('/master/rak/tambah', [RakController::class, 'create'])->name('rak.create');
    Route::post('/master/rak', [RakController::class, 'store'])->name('rak.store');
    Route::post('/master/rak/generate-ddc', [RakController::class, 'generateDdc'])->name('rak.generate_ddc');
    Route::post('/master/rak/map-items-ddc', [RakController::class, 'mapItemsByDdc'])->name('rak.map_items_ddc');

    Route::get('/master/rak/{id}/edit', [RakController::class, 'edit'])->whereNumber('id')->name('rak.edit');
    Route::put('/master/rak/{id}', [RakController::class, 'update'])->whereNumber('id')->name('rak.update');
    Route::delete('/master/rak/{id}', [RakController::class, 'destroy'])->whereNumber('id')->name('rak.destroy');

    Route::post('/master/rak/{id}/toggle', [RakController::class, 'toggleActive'])->whereNumber('id')->name('rak.toggle');
});

/*
|--------------------------------------------------------------------------
| TRANSAKSI (STAFF)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', fn () => redirect()->route('transaksi.dashboard'))
    ->name('dashboard')
    ->middleware(['auth', 'role.any:super_admin,admin,staff']);

Route::middleware(['auth', 'role.any:super_admin,admin,staff', 'track.circulation.metrics'])
    ->prefix('transaksi')
    ->name('transaksi.')
    ->group(function () {

        Route::get('/', [TransaksiController::class, 'index'])->name('index');
        Route::get('/pinjam', [TransaksiController::class, 'pinjamForm'])->name('pinjam.form');

        Route::get('/pinjam/cari-member', [TransaksiController::class, 'cariMember'])->name('pinjam.cari_member');
        Route::get('/pinjam/member-info/{id}', [TransaksiController::class, 'memberInfo'])
            ->whereNumber('id')
            ->name('pinjam.member_info');
        Route::get('/pinjam/cek-barcode', [TransaksiController::class, 'cekBarcode'])->name('pinjam.cek_barcode');
        Route::post('/pinjam', [TransaksiController::class, 'storePinjam'])->name('pinjam.store');
        Route::post('/unified/commit', [TransaksiController::class, 'unifiedCommit'])
            ->middleware('throttle:240,1')
            ->name('unified.commit');
        Route::post('/unified/sync', [TransaksiController::class, 'unifiedSync'])
            ->middleware('throttle:120,1')
            ->name('unified.sync');

        Route::get('/pinjam/sukses/{id}', [TransaksiController::class, 'pinjamSuccess'])
            ->whereNumber('id')
            ->name('pinjam.success');

        Route::get('/kembali', [TransaksiController::class, 'kembaliForm'])->name('kembali.form');
        Route::get('/kembali/cek-barcode', [TransaksiController::class, 'cekBarcodeKembali'])->name('kembali.cek_barcode');
        Route::post('/kembali', [TransaksiController::class, 'storeKembali'])->name('kembali.store');

        Route::get('/kembali/sukses/{id}', [TransaksiController::class, 'kembaliSuccess'])
            ->whereNumber('id')
            ->name('kembali.success');

        Route::get('/perpanjang', [TransaksiController::class, 'perpanjangForm'])->name('perpanjang.form');
        Route::get('/perpanjang/cek-barcode', [TransaksiController::class, 'cekBarcodePerpanjang'])->name('perpanjang.cek_barcode');
        Route::post('/perpanjang', [TransaksiController::class, 'storePerpanjang'])->name('perpanjang.store');

        Route::get('/riwayat', [TransaksiController::class, 'riwayat'])->name('riwayat');
        Route::get('/riwayat/{id}', [TransaksiController::class, 'detail'])
            ->whereNumber('id')
            ->name('riwayat.detail');

        Route::get('/riwayat/{id}/print', [TransaksiController::class, 'printSlip'])
            ->whereNumber('id')
            ->name('riwayat.print');

        Route::get('/quick-search', [TransaksiController::class, 'quickSearch'])->name('quick_search');

        Route::get('/dashboard', [TransaksiDashboardController::class, 'index'])->name('dashboard');
        Route::get('/metrics', CirculationMetricsController::class)
            ->middleware('throttle:120,1')
            ->name('metrics');
        Route::get('/exceptions', [CirculationExceptionController::class, 'index'])->name('exceptions.index');
        Route::get('/exceptions/export/csv', [CirculationExceptionController::class, 'exportCsv'])->name('exceptions.export.csv');
        Route::get('/exceptions/export/xlsx', [CirculationExceptionController::class, 'exportXlsx'])->name('exceptions.export.xlsx');
        Route::post('/exceptions/ack', [CirculationExceptionController::class, 'acknowledge'])
            ->middleware('throttle:120,1')
            ->name('exceptions.ack');
        Route::post('/exceptions/resolve', [CirculationExceptionController::class, 'resolve'])
            ->middleware('throttle:120,1')
            ->name('exceptions.resolve');
        Route::post('/exceptions/assign-owner', [CirculationExceptionController::class, 'assignOwner'])
            ->middleware('throttle:120,1')
            ->name('exceptions.assign_owner');
        Route::post('/exceptions/bulk-assign-owner', [CirculationExceptionController::class, 'bulkAssignOwner'])
            ->middleware('throttle:60,1')
            ->name('exceptions.bulk_assign_owner');
        Route::post('/exceptions/bulk', [CirculationExceptionController::class, 'bulkUpdate'])
            ->middleware('throttle:60,1')
            ->name('exceptions.bulk');
        Route::get('/policies', [CirculationPolicyController::class, 'index'])
            ->middleware('role.any:super_admin,admin')
            ->name('policies.index');
        Route::post('/policies/rules', [CirculationPolicyController::class, 'storeRule'])
            ->middleware('role.any:super_admin,admin')
            ->name('policies.rules.store');
        Route::put('/policies/rules/{id}', [CirculationPolicyController::class, 'updateRule'])
            ->whereNumber('id')
            ->middleware('role.any:super_admin,admin')
            ->name('policies.rules.update');
        Route::delete('/policies/rules/{id}', [CirculationPolicyController::class, 'deleteRule'])
            ->whereNumber('id')
            ->middleware('role.any:super_admin,admin')
            ->name('policies.rules.delete');
        Route::post('/policies/calendars', [CirculationPolicyController::class, 'storeCalendar'])
            ->middleware('role.any:super_admin,admin')
            ->name('policies.calendars.store');
        Route::post('/policies/closures', [CirculationPolicyController::class, 'storeClosure'])
            ->middleware('role.any:super_admin,admin')
            ->name('policies.closures.store');
        Route::delete('/policies/closures/{id}', [CirculationPolicyController::class, 'deleteClosure'])
            ->whereNumber('id')
            ->middleware('role.any:super_admin,admin')
            ->name('policies.closures.delete');
        Route::post('/policies/simulate', [CirculationPolicyController::class, 'simulate'])
            ->middleware('role.any:super_admin,admin')
            ->name('policies.simulate');

        Route::get('/denda', [TransaksiController::class, 'finesIndex'])->name('denda.index');
        Route::post('/denda/recalc', [TransaksiController::class, 'finesRecalc'])->name('denda.recalc');
        Route::post('/denda/bayar', [TransaksiController::class, 'finesPay'])->name('denda.bayar');
        Route::post('/denda/void', [TransaksiController::class, 'finesVoid'])->name('denda.void');
    });

/*
|--------------------------------------------------------------------------
| ACQUISITIONS (PENGADAAN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:viewAny,App\\Models\\AcquisitionRequest', 'role.any:super_admin,admin,staff'])
    ->prefix('acquisitions')
    ->name('acquisitions.')
    ->group(function () {
        // Requests
        Route::get('/requests', [AcquisitionsRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/create', [AcquisitionsRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests', [AcquisitionsRequestController::class, 'store'])->name('requests.store');
        Route::get('/requests/{id}', [AcquisitionsRequestController::class, 'show'])->whereNumber('id')->name('requests.show');
        Route::post('/requests/{id}/review', [AcquisitionsRequestController::class, 'review'])->whereNumber('id')->name('requests.review');
        Route::post('/requests/{id}/approve', [AcquisitionsRequestController::class, 'approve'])->whereNumber('id')->name('requests.approve');
        Route::post('/requests/{id}/reject', [AcquisitionsRequestController::class, 'reject'])->whereNumber('id')->name('requests.reject');
        Route::post('/requests/{id}/convert-to-po', [AcquisitionsRequestController::class, 'convertToPo'])->whereNumber('id')->name('requests.convert');
        Route::post('/requests/{id}/estimate', [AcquisitionsRequestController::class, 'updateEstimate'])->whereNumber('id')->name('requests.update_estimate');
        Route::post('/requests/bulk-convert', [AcquisitionsRequestController::class, 'bulkConvert'])->name('requests.bulk_convert');

        // Purchase Orders
        Route::get('/pos', [PurchaseOrderController::class, 'index'])->name('pos.index');
        Route::get('/pos/create', [PurchaseOrderController::class, 'create'])->name('pos.create');
        Route::post('/pos', [PurchaseOrderController::class, 'store'])->name('pos.store');
        Route::get('/pos/{id}', [PurchaseOrderController::class, 'show'])->whereNumber('id')->name('pos.show');
        Route::post('/pos/{id}/add-line', [PurchaseOrderController::class, 'addLine'])->whereNumber('id')->name('pos.add_line');
        Route::post('/pos/{id}/order', [PurchaseOrderController::class, 'order'])->whereNumber('id')->name('pos.order');
        Route::post('/pos/{id}/cancel', [PurchaseOrderController::class, 'cancel'])->whereNumber('id')->name('pos.cancel');
        Route::post('/pos/{id}/receive', [PurchaseOrderController::class, 'receive'])->whereNumber('id')->name('pos.receive');

        // Vendors
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::get('/vendors/create', [VendorController::class, 'create'])->name('vendors.create');
        Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::get('/vendors/{id}/edit', [VendorController::class, 'edit'])->whereNumber('id')->name('vendors.edit');
        Route::put('/vendors/{id}', [VendorController::class, 'update'])->whereNumber('id')->name('vendors.update');
        Route::delete('/vendors/{id}', [VendorController::class, 'destroy'])->whereNumber('id')->name('vendors.destroy');

        // Budgets
        Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
        Route::get('/budgets/create', [BudgetController::class, 'create'])->name('budgets.create');
        Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
        Route::get('/budgets/{id}/edit', [BudgetController::class, 'edit'])->whereNumber('id')->name('budgets.edit');
        Route::put('/budgets/{id}', [BudgetController::class, 'update'])->whereNumber('id')->name('budgets.update');
        Route::delete('/budgets/{id}', [BudgetController::class, 'destroy'])->whereNumber('id')->name('budgets.destroy');
    });

/*
|--------------------------------------------------------------------------
| RESERVASI
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    Route::get('/reservasi', [ReservasiController::class, 'index'])->name('reservasi.index');
    Route::post('/reservasi', [ReservasiController::class, 'store'])->name('reservasi.store');

    Route::post('/reservasi/{id}/batal', [ReservasiController::class, 'cancel'])
        ->whereNumber('id')
        ->name('reservasi.cancel');

    Route::post('/reservasi/{id}/penuhi', [ReservasiController::class, 'fulfill'])
        ->whereNumber('id')
        ->middleware(['role.any:super_admin,admin,staff'])
        ->name('reservasi.fulfill');

    Route::get('/reservasi/metrics', ReservationMetricsController::class)
        ->middleware(['role.any:super_admin,admin,staff'])
        ->name('reservasi.metrics');
});

Route::middleware(['auth', 'role.any:super_admin,admin,staff'])->group(function () {
    Route::get('/reservasi/rules', [ReservationPolicyController::class, 'index'])->name('reservasi.rules.index');
    Route::post('/reservasi/rules', [ReservationPolicyController::class, 'store'])->name('reservasi.rules.store');
    Route::post('/reservasi/rules/{id}/toggle', [ReservationPolicyController::class, 'toggle'])
        ->whereNumber('id')
        ->name('reservasi.rules.toggle');
});

/*
|--------------------------------------------------------------------------
| NOTIFIKASI
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifikasi.index');
    Route::get('/notifikasi/count', [NotificationController::class, 'unreadCount'])->name('notifikasi.count');

    Route::post('/notifikasi/{id}/read', [NotificationController::class, 'markRead'])
        ->whereNumber('id')
        ->name('notifikasi.read');

    Route::post('/notifikasi/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifikasi.read_all');
});


Route::middleware(['auth', 'role.any:super_admin,admin'])
    ->prefix('admin/marc')
    ->name('admin.marc.')
    ->group(function () {
        Route::get('/settings', [MarcSettingsController::class, 'index'])
            ->name('settings');
        Route::post('/settings', [MarcSettingsController::class, 'update'])
            ->name('settings.update');
        Route::post('/settings/reset', [MarcSettingsController::class, 'reset'])
            ->name('settings.reset');
        Route::post('/settings/preview', [MarcSettingsController::class, 'preview'])
            ->name('settings.preview');
        Route::post('/policy/draft', [MarcSettingsController::class, 'savePolicyDraft'])
            ->name('policy.draft');
        Route::post('/policy/publish', [MarcSettingsController::class, 'publishPolicy'])
            ->name('policy.publish');
        Route::get('/policy', [MarcPolicyApiController::class, 'index'])
            ->name('policy.api.list');
        Route::post('/policy', [MarcPolicyApiController::class, 'draft'])
            ->name('policy.api.draft');
        Route::post('/policy/publish-json', [MarcPolicyApiController::class, 'publish'])
            ->name('policy.api.publish');
        Route::get('/policy/audits', [MarcPolicyApiController::class, 'audits'])
            ->name('policy.api.audits');
        Route::get('/policy/audits.csv', [MarcPolicyApiController::class, 'auditsCsv'])
            ->name('policy.api.audits.csv');
    });

Route::middleware(['auth', 'role.any:super_admin,admin,staff'])
    ->get('/interop/metrics', InteropMetricsController::class)
    ->name('interop.metrics');
Route::middleware(['auth', 'role.any:super_admin,admin'])
    ->get('/interop/metrics/export/csv', [InteropMetricsController::class, 'exportCsv'])
    ->name('interop.metrics.export.csv');


/*
|--------------------------------------------------------------------------
| Placeholder lainnya
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::get('/komunitas', fn () => view('placeholders.page', [
        'title' => 'Komunitas',
        'subtitle' => 'Feed komunitas literasi',
        'tone' => 'green',
        'primary' => ['label' => 'Buat Postingan', 'href' => route('komunitas.buat')],
    ]))->name('komunitas.feed');

    Route::get('/komunitas/buat', fn () => view('placeholders.page', [
        'title' => 'Buat Postingan',
        'subtitle' => 'Tulis cerita / unggah gambar',
        'tone' => 'green',
        'primary' => ['label' => 'Publikasikan', 'href' => '#'],
    ]))->name('komunitas.buat');
    
    Route::get('/literasi', fn () => view('placeholders.page', [
        'title' => 'Literasi Digital',
        'subtitle' => 'Tips membaca & literasi informasi',
        'tone' => 'purple',
        'primary' => ['label' => 'Pelajari Lebih', 'href' => '#'],
    ]))->name('literasi.digital');
});

/*
|--------------------------------------------------------------------------
| Breeze auth routes
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';

Route::middleware(['auth:sanctum'])
    ->prefix('api/v1')
    ->name('api.v1.')
    ->group(function () {
        // Book Search API
        Route::prefix('books')->group(function () {
            Route::get('/search', [CatalogSearchController::class, 'apiSearch'])
                ->name('books.search');
            
            Route::get('/{id}', [CatalogSearchController::class, 'apiShow'])
                ->whereNumber('id')
                ->name('books.show');
            
            Route::get('/categories', [PustakawanDigitalController::class, 'getPopularCategories'])
                ->name('books.categories');
        });
    });

/*
|--------------------------------------------------------------------------
| Fallback Route
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    if (Auth::check()) {
        return redirect()->route('beranda');
    }
    return redirect()->route('login');
});
