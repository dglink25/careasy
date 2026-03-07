<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\EntrepriseController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\Admin\EntrepriseAdminController;
use App\Http\Controllers\API\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\UserSettingsController; 
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\API\AiServiceController;
use App\Http\Controllers\API\AiMessageController;
use App\Http\Controllers\API\AiLocationController;
use App\Http\Controllers\API\AiLogController;
use App\Http\Controllers\API\RendezVousController;

use App\Http\Controllers\API\PlanController;

Route::get('/test', fn() => ['status' => 'API OK', 'version' => '1.0']);

require __DIR__.'/auth.php';

Route::middleware('auth:sanctum')->group(function () {

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

    // STATUT EN LIGNE - NOUVEAU
    Route::post('user/update-online-status', [MessageController::class, 'updateOnlineStatus']);
    Route::get('user/{userId}/online-status', [MessageController::class, 'checkOnlineStatus']);
    
    Route::post('/user/online-status', [MessageController::class, 'updateOnlineStatus']);
    
    //  NOUVEAU: Routes messagerie authentifiées
    Route::post('conversation/start', [MessageController::class, 'startConversation']);
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

Route::middleware('auth:sanctum')->group(function () {
    // Gestion du profil utilisateur
    Route::get('/user/profile', [UserSettingsController::class, 'getProfile']);
    Route::put('/user/profile', [UserSettingsController::class, 'updateProfile']);
    Route::put('/user/email', [UserSettingsController::class, 'updateEmail']);
    Route::put('/user/password', [UserSettingsController::class, 'updatePassword']);
    
    // Mise à jour complète en une seule requête
    Route::put('/user/update-all', [UserSettingsController::class, 'updateAll']);
    
    // Paramètres d'apparence
    Route::get('/user/settings', [UserSettingsController::class, 'getSettings']);
    Route::put('/user/settings', [UserSettingsController::class, 'updateSettings']);
    Route::put('/user/theme', [UserSettingsController::class, 'updateTheme']);
    
    // Paramètres de notifications
    Route::get('/user/notification-settings', [UserSettingsController::class, 'getNotificationSettings']);
    Route::put('/user/notification-settings', [UserSettingsController::class, 'updateNotificationSettings']);
    
    // Téléchargement de photo de profil
    Route::post('/user/profile-photo', [UserSettingsController::class, 'updateProfilePhoto']);
    Route::delete('/user/profile-photo', [UserSettingsController::class, 'deleteProfilePhoto']);
});


Route::prefix('ai')->group(function () {

    // Localisation Bénin
    Route::get('/locations',          [AiLocationController::class, 'search']);
    Route::get('/locations/communes', [AiLocationController::class, 'communes']);

    // Domaines de services
    Route::get('/domaines', [AiServiceController::class, 'domaines']);

    // Services proches (public — pas besoin d'auth pour la carte)
    Route::get('/services/nearby', [AiServiceController::class, 'nearby']);
    Route::get('/services',        [AiServiceController::class, 'index']);
});

Route::prefix('ai')->middleware('auth:sanctum')->group(function () {

    // Messages IA
    Route::post('/messages', [AiMessageController::class, 'store']);

    // Conversations
    Route::get('/conversations/{id}/messages', [AiMessageController::class, 'history']);

    // Sessions IA
    Route::post('/sessions', [AiLogController::class, 'saveSession']);

    // Logs IA
    Route::post('/logs', [AiLogController::class, 'store']);

    // Feedback
    Route::post('/feedback', [AiLogController::class, 'feedback']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('conversation/service', [MessageController::class, 'startServiceConversation']);

    Route::post('conversation/{id}/send', [MessageController::class, 'sendMessage']);
    
    // Indicateurs en temps réel
    Route::post('conversation/{id}/typing', [MessageController::class, 'typingIndicator']);
    Route::post('conversation/{id}/recording', [MessageController::class, 'recordingIndicator']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Rendez-vous
    Route::get('/rendez-vous', [RendezVousController::class, 'index']);
    Route::get('/rendez-vous/calendar', [RendezVousController::class, 'calendar']);
    Route::get('/rendez-vous/{id}', [RendezVousController::class, 'show']);
    Route::post('/rendez-vous', [RendezVousController::class, 'store']);
    Route::post('/rendez-vous/{id}/confirm', [RendezVousController::class, 'confirm']);
    Route::post('/rendez-vous/{id}/cancel', [RendezVousController::class, 'cancel']);
    Route::post('/rendez-vous/{id}/complete', [RendezVousController::class, 'complete']);
    
    // Créneaux disponibles
    Route::get('/services/{serviceId}/slots/{date}', [RendezVousController::class, 'getAvailableSlots']);
});




// Routes protégées par authentication
Route::middleware('auth:sanctum')->group(function () {
    
    // Routes pour prestataires (plans publics)
    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/{id}', [PlanController::class, 'show']);
    Route::get('/plans/compare/all', [PlanController::class, 'compare']);
    
});

// Routes admin (avec middleware admin)
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    
    // Gestion des plans
    Route::get('/plans', [AdminPlanController::class, 'index']);
    Route::post('/plans', [AdminPlanController::class, 'store']);
    Route::get('/plans/{id}', [AdminPlanController::class, 'show']);
    Route::put('/plans/{id}', [AdminPlanController::class, 'update']);
    Route::delete('/plans/{id}', [AdminPlanController::class, 'destroy']);
    Route::post('/plans/update-order', [AdminPlanController::class, 'updateOrder']);
    Route::patch('/plans/{id}/toggle-status', [AdminPlanController::class, 'toggleStatus']);
    
});