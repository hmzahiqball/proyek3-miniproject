<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');
Route::view('/panduan', 'pages.guide')->name('guide');
Route::view('/tentang', 'pages.about')->name('about');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [AuthenticatedSessionController::class, 'create'])->name('access');
    Route::post('/masuk', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::view('/dashboard', 'pages.dashboard')->middleware('active')->name('dashboard');
    Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
