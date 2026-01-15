<?php
// routes/api.php - VERSION CORRIGÉE

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\EntrepriseController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\Admin\EntrepriseAdminController;
use App\Http\Controllers\API\MessageController;

Route::get('/test', fn() => ['status' => 'API OK', 'version' => '1.0']);

require __DIR__.'/auth.php';

/**
 * AUTHENTIFIÉ - Token requis
 */
Route::middleware('auth:sanctum')->group(function () {
    
    // MES ENTREPRISES - DOIT ÊTRE AVANT /entreprises/{id}
    Route::get('entreprises/mine', [EntrepriseController::class, 'mine']);
    Route::post('entreprises', [EntrepriseController::class, 'store']);

    Route::put('entreprises/{id}', [EntrepriseController::class, 'update']);
    Route::post('entreprises/{id}/complete-profile', [EntrepriseController::class, 'completeProfile']);
    

    // MES SERVICES - DOIT ÊTRE AVANT /services
    Route::get('services/mine', [ServiceController::class, 'mine']);
    Route::post('services', [ServiceController::class, 'store']);
    
    //  MESSAGERIE - ROUTES AUTHENTIFIÉES
    Route::get('conversations', [MessageController::class, 'myConversations']);
    Route::post('conversation/{id}/mark-read', [MessageController::class, 'markAsRead']);

    // 👉 STATUT EN LIGNE - NOUVEAU
    Route::post('user/update-online-status', [MessageController::class, 'updateOnlineStatus']);
    Route::get('user/{userId}/online-status', [MessageController::class, 'checkOnlineStatus']);
    
    Route::post('/user/online-status', [MessageController::class, 'updateOnlineStatus']);
    Route::get('/user/{userId}/online-status', [MessageController::class, 'checkOnlineStatus']);
    
    //  NOUVEAU: Routes messagerie authentifiées
    Route::post('conversation/start', [MessageController::class, 'startConversation']);
    Route::post('conversation/{id}/send', [MessageController::class, 'sendMessage']);
    Route::get('conversation/{id}', [MessageController::class, 'getMessages']);

    // Services
    Route::put('services/{id}', [ServiceController::class, 'update']);
    Route::delete('services/{id}', [ServiceController::class, 'destroy']);
    Route::get('services/{id}', [ServiceController::class, 'show']);

    // Entreprise – completion profil
    Route::post('entreprises/{id}/complete-profile', [EntrepriseController::class, 'completeProfile']);
});

/**
 * PUBLIC - Pas besoin d'authentification
 */
Route::get('entreprises', [EntrepriseController::class, 'index']);
Route::get('entreprises/domaine/{id}', [EntrepriseController::class, 'indexByDomaine']);
Route::get('entreprises/{id}', [EntrepriseController::class, 'show']);
Route::get('search', [EntrepriseController::class, 'search']);
Route::get('services', [ServiceController::class, 'index']);
Route::get('services/{id}', [ServiceController::class, 'show']); //  AJOUT - Détails service
// Form data pour création
    Route::get('entreprises/form/data', [EntrepriseController::class, 'getFormData']);   
/**
 * ADMIN - Gestion des entreprises
 */
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('entreprises', [EntrepriseAdminController::class, 'index']);
    Route::get('entreprises/{id}', [EntrepriseAdminController::class, 'show']);
    Route::post('entreprises/{id}/approve', [EntrepriseAdminController::class, 'approve']);
    Route::post('entreprises/{id}/reject', [EntrepriseAdminController::class, 'reject']);
});