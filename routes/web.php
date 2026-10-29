<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');
Route::view('/panduan', 'pages.guide')->name('guide');
Route::view('/tentang', 'pages.about')->name('about');
Route::view('/masuk', 'pages.access')->name('access');
