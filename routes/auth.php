<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpPasswordResetController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.store');

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

Route::post('/check-email', [RegisteredUserController::class, 'checkEmail']);
Route::post('/check-phone', [RegisteredUserController::class, 'checkPhone']);


Route::prefix('forgot-password')->middleware(['guest', 'throttle:5,1'])->group(function () {

    // Étape 1 : Envoyer le code OTP (email ou SMS selon identifiant)
    Route::post('/otp',        [OtpPasswordResetController::class, 'sendOtp'])
         ->name('otp.send');

    // Étape 1b : Renvoyer le code
    Route::post('/otp/resend', [OtpPasswordResetController::class, 'resendOtp'])
         ->name('otp.resend');

    // Étape 2 : Vérifier le code OTP
    Route::post('/otp/verify', [OtpPasswordResetController::class, 'verifyOtp'])
         ->name('otp.verify');

    // Étape 3 : Réinitialiser le mot de passe
    Route::post('/otp/reset',  [OtpPasswordResetController::class, 'resetPassword'])
         ->name('otp.reset');
});