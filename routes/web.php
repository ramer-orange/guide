<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItinerariesController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

//Route::view('/', 'welcome');
//
//Route::view('dashboard', 'dashboard')
//    ->middleware(['auth', 'verified'])
//    ->name('dashboard');
//
//Route::view('profile', 'profile')
//    ->middleware(['auth'])
//    ->name('profile');

require __DIR__.'/auth.php';

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/itineraries/index', [ItinerariesController::class, 'index'])
    ->middleware('auth')
    ->name('itineraries.index');
Route::get('/itineraries/create', [ItinerariesController::class, 'create'])
    ->middleware('auth')
    ->name('itineraries.create');
Route::post('/itineraries', [ItinerariesController::class, 'store'])
    ->name('itineraries.store');
Route::get('/itineraries/{overview}/edit', [ItinerariesController::class, 'edit'])
    ->middleware('auth')
    ->name('itineraries.edit');
Route::put('/itineraries/{overview}', [ItinerariesController::class, 'update'])
    ->name('itineraries.update');





Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

