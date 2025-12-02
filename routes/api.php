<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\EntrepriseController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\Admin\EntrepriseAdminController;
use App\Http\Controllers\MessageController;


Route::get('/test', fn() => ['status' => 'API OK', 'version' => '1.0']);

require __DIR__.'/auth.php';

/**
 * AUTHENTIFIÉ - Token requis (AVANT les routes publiques !)
 */
Route::middleware('auth:sanctum')->group(function () {
    // Form data pour création
    Route::get('entreprises/form/data', [EntrepriseController::class, 'getFormData']);
    
    // MES ENTREPRISES - DOIT ÊTRE AVANT /entreprises/{id}
    Route::get('entreprises/mine', [EntrepriseController::class, 'mine']);
    Route::post('entreprises', [EntrepriseController::class, 'store']);

    // MES SERVICES - DOIT ÊTRE AVANT /services
    Route::get('services/mine', [ServiceController::class, 'mine']);
    Route::post('services', [ServiceController::class, 'store']);
});

/**
 * PUBLIC - Pas besoin d'authentification
 */
Route::get('entreprises', [EntrepriseController::class, 'index']);
Route::get('entreprises/domaine/{id}', [EntrepriseController::class, 'indexByDomaine']);
Route::get('entreprises/{id}', [EntrepriseController::class, 'show']);
Route::get('search', [EntrepriseController::class, 'search']);
Route::get('services', [ServiceController::class, 'index']);

/**
 * ADMIN - Gestion des entreprises
 */
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('entreprises', [EntrepriseAdminController::class, 'index']);
    Route::get('entreprises/{id}', [EntrepriseAdminController::class, 'show']);
    Route::post('entreprises/{id}/approve', [EntrepriseAdminController::class, 'approve']);
    Route::post('entreprises/{id}/reject', [EntrepriseAdminController::class, 'reject']);
});

Route::post('/conversation/start', [MessageController::class, 'startConversation']);
Route::post('/conversation/{id}/send', [MessageController::class, 'sendMessage']);
Route::get('/conversation/{id}', [MessageController::class, 'getMessages']);