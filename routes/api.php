<?php

use App\Http\Controllers\Api\IngestEventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Server-to-server event ingest for Dot apps. No session/Sanctum: the caller
// proves itself with a per-platform HMAC signature (see IngestSignature) and is
// rate limited per platform + company inside the controller.
Route::post('/ingest/events', IngestEventController::class)->name('api.ingest.events');
