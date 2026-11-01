<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Coordinator\AreaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Reporter\ReportController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');
Route::view('/panduan', 'pages.guide')->name('guide');
Route::view('/tentang', 'pages.about')->name('about');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [AuthenticatedSessionController::class, 'create'])->name('access');
    Route::post('/masuk', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:pelapor')->prefix('pelapor')->name('pelapor.')->group(function (): void {
        Route::get('/laporan/buat', [ReportController::class, 'create'])->name('reports.create');
        Route::post('/laporan', [ReportController::class, 'store'])->name('reports.store');
    });

    Route::middleware('role:koordinator')->prefix('koordinator')->name('koordinator.')->group(function (): void {
        Route::resource('wilayah', AreaController::class)
            ->parameters(['wilayah' => 'area'])->names('areas')->except('show');
    });

    Route::view('/pelapor/dashboard', 'pages.dashboard', [
        'title' => 'Dashboard Pelapor',
        'responsibilities' => [
            'Membuat laporan kejadian beserta informasi lokasi.',
            'Melampirkan satu bukti pendukung jika tersedia.',
            'Memperbaiki laporan selama status masih mengizinkan.',
            'Memantau perkembangan laporan milik sendiri.',
        ],
    ])
        ->middleware('role:pelapor')->name('pelapor.dashboard');
    Route::view('/relawan/dashboard', 'pages.dashboard', [
        'title' => 'Dashboard Relawan Pemantau',
        'responsibilities' => [
            'Meninjau kelengkapan dan kewajaran informasi laporan.',
            'Melihat laporan terkait pada wilayah yang sama.',
            'Memberikan catatan perbaikan atau kebutuhan penerusan.',
            'Memperbarui perkembangan laporan sesuai kewenangan.',
        ],
    ])
        ->middleware('role:relawan')->name('relawan.dashboard');
    Route::view('/koordinator/dashboard', 'pages.dashboard', [
        'title' => 'Dashboard Koordinator Relawan',
        'responsibilities' => [
            'Mengelola data wilayah dan kebutuhan akses relawan.',
            'Mencatat penerusan informasi kepada pihak terkait.',
            'Mencatat penyelesaian proses setelah penerusan.',
            'Melihat rekap laporan.',
        ],
    ])
        ->middleware('role:koordinator')->name('koordinator.dashboard');
});

Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
