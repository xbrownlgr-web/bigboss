<?php

use App\Http\Controllers\API\AssistantController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\PronosticController;
use App\Http\Controllers\API\SettingController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\VipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Limite anti-force brute et anti-spam d'e-mails.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::get('/pronostics', [PronosticController::class, 'index']);
    Route::get('/pronostics/history', [PronosticController::class, 'history']);
    Route::post('/vip/maketou/webhook', [VipController::class, 'webhook']);
    Route::get('/settings/public', [SettingController::class, 'public']);
    Route::get('/app/version', [SettingController::class, 'appVersion']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/delete-account', [AuthController::class, 'deleteAccount'])->middleware('throttle:5,1');
        Route::put('/auth/fcm-token', [AuthController::class, 'updateFcmToken']);

        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show']);

        // Réservé au compte admin (utilisé par le service IA) : sans ça, n'importe quel
        // utilisateur de l'app pouvait publier un pronostic et notifier tout le monde.
        Route::middleware('admin')->group(function () {
            Route::post('/pronostics', [PronosticController::class, 'store']);
            Route::post('/pronostics/{id}/result', [PronosticController::class, 'result']);
        });

        Route::post('/vip/requests', [VipController::class, 'requestAccess'])->middleware('throttle:10,1');
        Route::get('/vip/me', [VipController::class, 'me']);
        Route::post('/vip/checkout', [VipController::class, 'checkout'])->middleware('throttle:10,1');
        Route::post('/vip/checkout/confirm', [VipController::class, 'confirm'])->middleware('throttle:30,1');
        Route::get('/vip/free', [VipController::class, 'freeConfig']);
        Route::post('/vip/free', [VipController::class, 'submitFreeClaim'])->middleware('throttle:6,1');

        Route::get('/messages', [MessageController::class, 'index']);
        Route::post('/messages', [MessageController::class, 'store'])->middleware('throttle:20,1');

        // Assistant 24h/24 (IA) : chaque message coûte un appel OpenRouter, d'où la limite par minute.
        Route::get('/assistant/messages', [AssistantController::class, 'index']);
        Route::post('/assistant/messages', [AssistantController::class, 'store'])->middleware('throttle:12,1');
        Route::delete('/assistant/messages', [AssistantController::class, 'destroy']);
    });
});
