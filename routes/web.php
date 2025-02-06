<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItinerariesController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\SharedPasswordController;
use App\Http\Middleware\CheckAuthOrSharedAccess;

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
Route::get('/itineraries/{overview}/edit', [ItinerariesController::class, 'edit'])
    ->middleware(CheckAuthOrSharedAccess::class)
    ->name('itineraries.edit');
Route::delete('/itineraries/index/{overview}', [ItinerariesController::class, 'destroy'])
    ->middleware('auth')
    ->name('itineraries.index.destroy');

Route::get('/itineraries/{id}/shared-access', [SharedPasswordController::class, 'show'])
    ->name('shared-access.show');
Route::post('/itineraries/{id}/shared-access', [SharedPasswordController::class, 'verify'])
    ->name('shared-access.verify');

Route::get('/policy', function () {
    return view('policy');
});
Route::get('/terms', function () {
    return view('terms');
});





Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

