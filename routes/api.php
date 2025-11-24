<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\EntrepriseController;
use App\Http\Controllers\API\ServiceController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/test', function () {
    return 'OK API';
});

require __DIR__.'/auth.php';

Route::get('entreprises', [EntrepriseController::class, 'index']);
Route::get('entreprises/{id}', [EntrepriseController::class, 'show']);
Route::post('entreprises', [EntrepriseController::class, 'store'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/service', [ServiceController::class, 'store']);
    Route::get('/service', [ServiceController::class, 'index']);
    Route::get('/service/{id}', [ServiceController::class, 'show']);
});
