<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\API\EntrepriseController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\Admin\EntrepriseAdminController;
use App\Http\Controllers\API\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\API\Admin\SignalementAdminController;
use App\Http\Controllers\API\Admin\AbonnementAdminController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\UserSettingsController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\API\AiServiceController;
use App\Http\Controllers\API\AiMessageController;
use App\Http\Controllers\API\AiLocationController;
use App\Http\Controllers\API\AiLogController;
use App\Http\Controllers\API\RendezVousController;
use App\Http\Controllers\API\PaiementController;
use App\Http\Controllers\API\AbonnementController;
use App\Http\Controllers\API\PlanController;
use App\Http\Controllers\BroadcastingController;
use App\Http\Controllers\API\PushNotificationController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\Auth\QrLoginController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\VerifyContactController; // ← déjà importé
use App\Http\Controllers\API\CarAIController;
use App\Http\Controllers\API\Admin\SmsAdminController;
use App\Http\Controllers\API\NotificationSettingsController;

Route::get('/test', fn() => ['status' => 'API OK', 'version' => '1.0']);

require __DIR__.'/auth.php';


Route::prefix('verify-contact')->middleware('throttle:10,1')->group(function () {
    Route::post('/send',  [VerifyContactController::class, 'send'])->name('verify.contact.send');
    Route::post('/check', [VerifyContactController::class, 'check'])->name('verify.contact.check');
});

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/qr-login', [QrLoginController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::put('/user/phone', [UserSettingsController::class, 'updatePhone']);

    // ── Pusher broadcasting auth ──────────────────────────────────────────
    Route::post('/broadcasting/auth', fn(\Illuminate\Http\Request $r) => Broadcast::auth($r));
    Route::post('/pusher/auth',       fn(\Illuminate\Http\Request $r) => Broadcast::auth($r));

    // ── Notifications ─────────────────────────────────────────────────────
    Route::get('/notifications',                [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',   [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/mark-read',[NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}',        [NotificationController::class, 'destroy']);

    // ── Push Web VAPID ────────────────────────────────────────────────────
    Route::get('/push/vapid-public-key', [PushNotificationController::class, 'vapidPublicKey']);
    Route::post('/push/subscribe',       [PushNotificationController::class, 'subscribe']);
    Route::post('/push/unsubscribe',     [PushNotificationController::class, 'unsubscribe']);

    // ── Entreprises ───────────────────────────────────────────────────────
    Route::get('entreprises/mine',                   [EntrepriseController::class, 'mine']);
    Route::post('entreprises',                       [EntrepriseController::class, 'store']);
    Route::get('mes-entreprises',                    [EntrepriseController::class, 'mine']);
    Route::put('entreprises/{id}',                   [EntrepriseController::class, 'update']);
    Route::post('entreprises/{id}/complete-profile', [EntrepriseController::class, 'completeProfile']);

    // ── Services ──────────────────────────────────────────────────────────
    Route::get('services/mine',    [ServiceController::class, 'mine']);
    Route::post('services',        [ServiceController::class, 'store']);
    Route::put('services/{id}',    [ServiceController::class, 'update']);
    Route::put('servicesMobile/{id}', [ServiceController::class, 'updateMobile']);
    Route::delete('services/{id}', [ServiceController::class, 'destroy']);
    Route::get('services/{id}',    [ServiceController::class, 'show']);
    Route::patch('services/{id}/toggle-visibility', [ServiceController::class, 'toggleVisibility']);

    // ── Messagerie ────────────────────────────────────────────────────────
    Route::get('conversations',                                         [MessageController::class, 'myConversations']);
    Route::post('conversation/start',                                   [MessageController::class, 'startConversation']);
    Route::post('conversation/service/{serviceId}/start',               [MessageController::class, 'startServiceConversationMobile']);
    Route::post('conversation/service',                                 [MessageController::class, 'startServiceConversation']);
    Route::get('conversation/{id}',                                     [MessageController::class, 'getMessages']);
    Route::delete('conversation/{id}',                                  [MessageController::class, 'destroyConversation']);
    Route::post('conversation/{id}/send',                               [MessageController::class, 'sendMessage']);
    Route::post('conversation/{id}/send-mobile',                        [MessageController::class, 'sendMessageMobile']);
    Route::post('conversation/{id}/mark-read',                          [MessageController::class, 'markAsRead']);
    Route::post('conversation/{id}/typing',                             [MessageController::class, 'typingIndicator']);
    Route::post('conversation/{id}/recording',                          [MessageController::class, 'recordingIndicator']);
    Route::put('messages/{id}',    [MessageController::class, 'update']);
    Route::delete('messages/{id}', [MessageController::class, 'destroy']);

    // ── Statut en ligne ───────────────────────────────────────────────────
    Route::post('user/update-online-status',   [MessageController::class, 'updateOnlineStatus']);
    Route::post('/user/online-status',         [MessageController::class, 'updateOnlineStatus']);
    Route::get('user/{userId}/online-status',  [MessageController::class, 'checkOnlineStatus']);
    Route::post('/user/fcm-token',             [MessageController::class, 'saveFcmToken']);

    // ── Profil utilisateur ────────────────────────────────────────────────
    Route::get('/user/profile',                      [UserSettingsController::class, 'getProfile']);
    Route::put('/user/profile',                      [UserSettingsController::class, 'updateProfile']);
    Route::put('/user/email',                        [UserSettingsController::class, 'updateEmail']);
    Route::put('/user/password',                     [UserSettingsController::class, 'updatePassword']);
    Route::put('/user/update-all',                   [UserSettingsController::class, 'updateAll']);
    Route::get('/user/settings',                     [UserSettingsController::class, 'getSettings']);
    Route::put('/user/settings',                     [UserSettingsController::class, 'updateSettings']);
    Route::put('/user/theme',                        [UserSettingsController::class, 'updateTheme']);
   
    Route::get('/user/notification-settings',        [NotificationSettingsController::class, 'index']);
    Route::put('/user/notification-settings',        [NotificationSettingsController::class, 'update']);
    
    Route::post('/user/profile-photo',               [UserSettingsController::class, 'updateProfilePhoto']);
    Route::delete('/user/profile-photo',             [UserSettingsController::class, 'deleteProfilePhoto']);
    Route::post('/check-email-availability',         [UserSettingsController::class, 'checkEmailAvailability']);
    Route::post('/check-phone-availability',         [UserSettingsController::class, 'checkPhoneAvailability']);

    // ════════════════════════════════════════════════════════════════════
    //  GESTION DES SESSIONS & SÉCURITÉ
    // ════════════════════════════════════════════════════════════════════
    Route::get('/user/sessions',                [SessionController::class, 'index']);
    Route::delete('/user/sessions/{id}',        [SessionController::class, 'revoke']);
    Route::post('/user/logout-all',             [SessionController::class, 'logoutAll']);
    Route::get('/user/login-history',           [SessionController::class, 'loginHistory']);

    // ── QR LOGIN — routes protégées ───────────────────────────────────────
    Route::post('/user/sessions/share-token',
        [QrLoginController::class, 'generate']);
    Route::get('/user/sessions/share-token/{token}/status',
        [QrLoginController::class, 'status'])
        ->where('token', '[A-Za-z0-9]{64}');

    // ── Rendez-vous ───────────────────────────────────────────────────────
    Route::get('/rendez-vous',                       [RendezVousController::class, 'index']);
    Route::get('/rendez-vous/calendar',              [RendezVousController::class, 'calendar']);
    Route::post('/rendez-vous',                      [RendezVousController::class, 'store']);
    Route::get('/rendez-vous/{id}',                  [RendezVousController::class, 'show']);
    Route::post('/rendez-vous/{id}/confirm',         [RendezVousController::class, 'confirm']);
    Route::post('/rendez-vous/{id}/cancel',          [RendezVousController::class, 'cancel']);
    Route::post('/rendez-vous/{id}/complete',        [RendezVousController::class, 'complete']);
    Route::get('/services/{serviceId}/slots/{date}', [RendezVousController::class, 'getAvailableSlots']);

    // ── Plans & Paiements ─────────────────────────────────────────────────
    Route::get('/plans',                           [PlanController::class, 'index']);
    Route::get('/plans/compare/all',               [PlanController::class, 'compare']);
    Route::get('/plans/{id}',                      [PlanController::class, 'show']);
    Route::post('/paiements/initier/{planId}',     [PaiementController::class, 'initierPaiement']);
    Route::get('/paiements/verifier/{reference}',  [PaiementController::class, 'verifierStatut']);
    Route::get('/abonnements',                     [AbonnementController::class, 'index']);
    Route::get('/abonnements/actif',               [AbonnementController::class, 'actif']);
    Route::get('/abonnements/{id}',                [AbonnementController::class, 'show']);
     Route::post('/abonnements/{id}/annuler',     [AbonnementController::class, 'annuler']);

    // ── Domaines & Recherche ──────────────────────────────────────────────
    Route::get('/domaines', [ServiceController::class, 'domaines']);
    Route::get('/search',   [ServiceController::class, 'search']);

    // ── Reviews ───────────────────────────────────────────────────────────
    Route::post('reviews/{rendezVous}',        [ReviewController::class, 'store']);
    Route::post('reviews/{rendezVous}/report', [ReviewController::class, 'report']);
});

// ── PUBLIC ────────────────────────────────────────────────────────────────────
Route::get('entreprises',               [EntrepriseController::class, 'index']);
Route::get('entreprises/domaine/{id}',  [EntrepriseController::class, 'indexByDomaine']);
Route::get('entreprises/form/data',     [EntrepriseController::class, 'getFormData']);
Route::get('entreprises/{id}',          [EntrepriseController::class, 'show']);
Route::get('/search',                   [ServiceController::class, 'search']);
Route::get('services',                  [ServiceController::class, 'index']);
Route::get('services/{id}',             [ServiceController::class, 'show']);
Route::get('services/{serviceId}/reviews', [ReviewController::class, 'forService']); 

// ── Admin ─────────────────────────────────────────────────────────────────────
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('entreprises',                  [EntrepriseAdminController::class, 'index']);
    Route::get('entreprises/{id}',             [EntrepriseAdminController::class, 'show']);
    Route::post('entreprises/{id}/approve',    [EntrepriseAdminController::class, 'approve']);
    Route::post('entreprises/{id}/reject',     [EntrepriseAdminController::class, 'reject']);
    Route::post('entreprises/{id}/extend-trial', [EntrepriseAdminController::class, 'extendTrial']);
    
    Route::get('/plans',                       [AdminPlanController::class, 'index']);
    Route::post('/plans',                      [AdminPlanController::class, 'store']);
    Route::get('/plans/{id}',                  [AdminPlanController::class, 'show']);
    Route::put('/plans/{id}',                  [AdminPlanController::class, 'update']);
    Route::delete('/plans/{id}',               [AdminPlanController::class, 'destroy']);
    Route::post('/plans/update-order',         [AdminPlanController::class, 'updateOrder']);
    Route::patch('/plans/{id}/toggle-status',  [AdminPlanController::class, 'toggleStatus']);
    
    Route::get('/abonnements',                 [AbonnementAdminController::class, 'index']);
    Route::get('/abonnements/{id}',            [AbonnementAdminController::class, 'show']);
    
    Route::get('/signalements',                [SignalementAdminController::class, 'index']);
    Route::patch('/signalements/{id}/resolve', [SignalementAdminController::class, 'resolve']);
    Route::patch('/signalements/{id}/dismiss', [SignalementAdminController::class, 'dismiss']);
    
    Route::post('/qr-tokens/purge',            [QrLoginController::class, 'purgeExpired']);
    Route::get('/sms/status',                  [SmsAdminController::class, 'status']);
    Route::post('/sms/test',                   [SmsAdminController::class, 'test']);
});

// ── IA ────────────────────────────────────────────────────────────────────────
Route::prefix('ai')->group(function () {
    Route::get('/locations',          [AiLocationController::class, 'search']);
    Route::get('/locations/communes', [AiLocationController::class, 'communes']);
    Route::get('/domaines',           [AiServiceController::class, 'domaines']);
    Route::get('/services/nearby',    [AiServiceController::class, 'nearby']);
    Route::get('/services',           [AiServiceController::class, 'index']);
});

Route::prefix('ai')->middleware('auth:sanctum')->group(function () {
    Route::post('/messages',                   [AiMessageController::class, 'store']);
    Route::get('/conversations/{id}/messages', [AiMessageController::class, 'history']);
    Route::post('/sessions',                   [AiLogController::class, 'saveSession']);
    Route::post('/logs',                       [AiLogController::class, 'store']);
    Route::post('/feedback',                   [AiLogController::class, 'feedback']);
});

// ── Paiement webhooks (public) ────────────────────────────────────────────────
Route::match(['get', 'post'], '/paiements/callback', [PaiementController::class, 'callback'])->name('paiements.callback');
Route::get('/paiements/success', [PaiementController::class, 'success'])->name('paiements.success');
Route::get('/paiements/cancel',  [PaiementController::class, 'cancel'])->name('paiements.cancel');

// ── Google Auth ───────────────────────────────────────────────────────────────
Route::get('/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('google.redirectMobile');
Route::post('/google/callback/mobile', [GoogleAuthController::class, 'handleGoogleCallbackMobile']);

Route::prefix('auth')->group(function () {
    Route::get('/google', [GoogleAuthController::class, 'redirectToGoogle'])
        ->name('api.google.redirect');
});

Route::prefix('carai')->middleware('auth:sanctum')->group(function () {
    Route::post('/conversations/start', [CarAIController::class, 'startConversation']);
    Route::post('/chat', [CarAIController::class, 'chat']);
    Route::get('/conversations/{id}/messages', [CarAIController::class, 'history']);
    Route::delete('/conversations/{id}', [CarAIController::class, 'clearHistory']);
});

Route::prefix('auth')->group(function () {
    Route::get('/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback');
});

Route::prefix('carai')->group(function () {
    Route::get('/nearby', [CarAIController::class, 'nearby']);
});