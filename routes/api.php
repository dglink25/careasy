<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\EntrepriseController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\Admin\EntrepriseAdminController;

Route::get('/test', fn() => 'API OK');

require __DIR__.'/auth.php';

/**
 * PUBLIC
 */
Route::get('entreprises', [EntrepriseController::class, 'index']);
Route::get('entreprises/domaine/{id}', [EntrepriseController::class, 'indexByDomaine']);
Route::get('entreprises/{id}', [EntrepriseController::class, 'show']);
Route::get('search', [EntrepriseController::class, 'search']);

Route::get('services', [ServiceController::class, 'index']);

/**
 * AUTHENTIFIÉ
 */
Route::middleware('auth:sanctum')->group(function () {

    // Entreprise
    Route::get('entreprises/mine', [EntrepriseController::class, 'mine']);
    Route::get('entreprises/form/data', [EntrepriseController::class, 'getFormData']);
    Route::post('entreprises', [EntrepriseController::class, 'store']);

    // Services
    Route::get('services/mine', [ServiceController::class, 'mine']);
    Route::post('services', [ServiceController::class, 'store']);
});

// Admin routes (auth required, controller checks role)
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('entreprises', [EntrepriseAdminController::class, 'index']); // list toutes
    Route::get('entreprises/{id}', [EntrepriseAdminController::class, 'show']); // details
    Route::post('entreprises/{id}/approve', [EntrepriseAdminController::class, 'approve']); // valider
    Route::post('entreprises/{id}/reject', [EntrepriseAdminController::class, 'reject']); // rejeter (admin_note required)
});
