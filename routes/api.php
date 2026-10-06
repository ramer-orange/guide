<?php

use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\PackingTemplateController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\ViewerShareController;
use App\Http\Middleware\PrivateApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware(['web', PrivateApiResponse::class])->group(function () {
    Route::get('/session', [SessionController::class, 'show'])->name('session.show');
    Route::get('/itineraries', [ItineraryController::class, 'index'])->middleware('auth:sanctum')->name('itineraries.index');
    Route::post('/itineraries', [ItineraryController::class, 'store'])->middleware('auth:sanctum')->name('itineraries.store');
    Route::get('/itineraries/{itinerary}', [ItineraryController::class, 'show'])->name('itineraries.show');
    Route::put('/itineraries/{itinerary}', [ItineraryController::class, 'update'])->middleware('auth:sanctum')->name('itineraries.update');
    Route::post('/itineraries/{itinerary}', [ItineraryController::class, 'update'])->middleware('auth:sanctum')->name('itineraries.update.multipart');
    Route::delete('/itineraries/{itinerary}', [ItineraryController::class, 'destroy'])->middleware('auth:sanctum')->name('itineraries.destroy');
    Route::get('/itineraries/{itinerary}/files/{fileId}', [ItineraryController::class, 'file'])->name('itineraries.files.show');
    Route::get('/itineraries/{itinerary}/files/{fileId}/preview', [ItineraryController::class, 'previewFile'])->name('itineraries.files.preview');
    Route::get('/packing-templates', [PackingTemplateController::class, 'index'])->name('packing-templates.index');
    Route::post('/itineraries/{itinerary}/members', [MemberController::class, 'store'])->middleware('auth:sanctum')->name('itineraries.members.store');
    Route::delete('/itineraries/{itinerary}/members/{member}', [MemberController::class, 'destroy'])->middleware('auth:sanctum')->name('itineraries.members.destroy');
    Route::get('/itineraries/{itinerary}/shared-access', [ViewerShareController::class, 'status'])->name('itineraries.shared-access.status');
    Route::post('/itineraries/{itinerary}/shared-access', [ViewerShareController::class, 'verify'])->name('itineraries.shared-access.verify');
    Route::put('/itineraries/{itinerary}/viewer-share', [ViewerShareController::class, 'update'])->middleware('auth:sanctum')->name('itineraries.viewer-share.update');
    Route::delete('/itineraries/{itinerary}/viewer-share', [ViewerShareController::class, 'destroy'])->middleware('auth:sanctum')->name('itineraries.viewer-share.destroy');
});
