<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
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

    Route::view('/pelapor/dashboard', 'pages.dashboard')
        ->middleware('role:pelapor')->name('pelapor.dashboard');
    Route::view('/relawan/dashboard', 'pages.dashboard')
        ->middleware('role:relawan')->name('relawan.dashboard');
    Route::view('/koordinator/dashboard', 'pages.dashboard')
        ->middleware('role:koordinator')->name('koordinator.dashboard');
});

Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
