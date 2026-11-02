<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\ModerationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('health', [MetaController::class, 'health']);
    Route::get('emergency-resources', [MetaController::class, 'resources']);
    Route::get('stats', [MetaController::class, 'stats']);
    Route::get('languages', [\App\Http\Controllers\Api\LanguageController::class, 'index']);
    Route::get('i18n/{code}', [\App\Http\Controllers\Api\LanguageController::class, 'bundle'])->where('code', '[a-zA-Z-]{2,12}')->middleware('throttle:ai');

    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/forgot', [\App\Http\Controllers\Api\PasswordResetController::class, 'forgot']);
        Route::post('auth/reset', [\App\Http\Controllers\Api\PasswordResetController::class, 'reset']);
        Route::post('auth/social/exchange', [SocialAuthController::class, 'exchange']);
    });
    Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->whereIn('provider', ['facebook', 'google']);
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])->whereIn('provider', ['facebook', 'google']);

    Route::get('media/{media}/file', [MediaController::class, 'show'])->middleware('signed')->name('media.file');

    Route::get('avatars/{user}', [AuthController::class, 'avatarFile'])->middleware('signed')->name('avatar.file');
    Route::get('incidents', [IncidentController::class, 'index']);
    Route::get('incidents/map', [IncidentController::class, 'map']);
    Route::get('incidents/{incident}', [IncidentController::class, 'show']);
    Route::get('nearby-help', [\App\Http\Controllers\Api\OrganizationController::class, 'nearby']);
    Route::get('incidents/{incident}/dispatches', [\App\Http\Controllers\Api\OrganizationController::class, 'forIncident']);
    Route::get('incidents/{incident}/comments', [IncidentController::class, 'comments']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::patch('auth/me', [AuthController::class, 'updateMe']);
        Route::delete('auth/me', [AuthController::class, 'destroyMe']);
        Route::post('auth/me/avatar', [AuthController::class, 'avatar'])->middleware('throttle:uploads');
        Route::post('auth/password', [AuthController::class, 'changePassword'])->middleware('throttle:auth');
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('media', [MediaController::class, 'store'])->middleware('throttle:uploads');
        Route::post('analyses', [AnalysisController::class, 'store'])->middleware('throttle:ai');
        Route::get('analyses/{id}', [AnalysisController::class, 'show']);

        Route::middleware('throttle:write')->group(function () {
            Route::get('incident-duplicates', [IncidentController::class, 'duplicates']);
            Route::post('incidents', [IncidentController::class, 'store']);
            Route::patch('incidents/{incident}', [IncidentController::class, 'update']);
            Route::delete('incidents/{incident}', [IncidentController::class, 'destroy']);
            Route::post('incidents/{id}/restore', [IncidentController::class, 'restore']);
            Route::get('me/trash', [IncidentController::class, 'trash']);
            Route::patch('comments/{id}', [IncidentController::class, 'updateComment'])->whereNumber('id');
            Route::delete('comments/{id}', [IncidentController::class, 'deleteComment'])->whereNumber('id');
            Route::post('comments/{id}/restore', [IncidentController::class, 'restoreComment'])->whereNumber('id');
            Route::post('comments/{id}/like', [IncidentController::class, 'likeComment'])->whereNumber('id');
            Route::post('incidents/{incident}/withdraw', [IncidentController::class, 'withdraw']);
            Route::post('incidents/{incident}/reactions', [IncidentController::class, 'react']);
            Route::post('incidents/{incident}/comments', [IncidentController::class, 'comment']);
        });

        Route::get('org/dispatches', [\App\Http\Controllers\Api\OrganizationController::class, 'mine']);
        Route::patch('org/me', [\App\Http\Controllers\Api\OrganizationController::class, 'updateMine'])->middleware('throttle:write');
        Route::post('org/dispatches/{dispatch}/status', [\App\Http\Controllers\Api\OrganizationController::class, 'setStatus'])->middleware('throttle:write');
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::delete('notifications', [NotificationController::class, 'clear']);
        Route::patch('notifications/{id}', [NotificationController::class, 'update'])->whereNumber('id');
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->whereNumber('id');

        // Admin data tables (CrudPolicy decides per entity): list, show, create, edit, Trash, restore, permanent delete.
        Route::middleware('role:moderator')->prefix('crud')->group(function () {
            $c = \App\Http\Controllers\Api\CrudController::class;
            Route::get('/', [$c, 'meta']);
            Route::get('{entity}', [$c, 'index']);
            Route::post('{entity}', [$c, 'store'])->middleware('throttle:write');
            Route::get('{entity}/{id}', [$c, 'show']);
            Route::patch('{entity}/{id}', [$c, 'update'])->middleware('throttle:write');
            Route::delete('{entity}/{id}', [$c, 'destroy'])->middleware('throttle:write');
            Route::post('{entity}/{id}/restore', [$c, 'restore'])->middleware('throttle:write');
            Route::delete('{entity}/{id}/force', [$c, 'forceDelete'])->middleware('throttle:write');
        });

        Route::prefix('mod')->middleware('role:moderator')->group(function () {
            Route::get('incidents', [ModerationController::class, 'queue']);
            Route::post('incidents/{incident}/status', [ModerationController::class, 'setStatus']);
            Route::post('incidents/{incident}/merge', [ModerationController::class, 'merge']);
            Route::get('users', [ModerationController::class, 'users']);
            Route::post('users/{user}/role', [ModerationController::class, 'setRole']);
            Route::get('audit-logs', [ModerationController::class, 'audit']);
            Route::post('emergency-resources/{resource}/verify', [ModerationController::class, 'verifyResource']);
            Route::middleware('role:admin')->prefix('admin')->group(function () {
                Route::get('overview', [\App\Http\Controllers\Api\AdminController::class, 'overview']);
                Route::get('emergency-resources', [\App\Http\Controllers\Api\AdminController::class, 'resources']);
                Route::post('emergency-resources', [\App\Http\Controllers\Api\AdminController::class, 'storeResource']);
                Route::get('camera-feeds', [\App\Http\Controllers\Api\CameraFeedController::class, 'index']);
                Route::post('camera-feeds', [\App\Http\Controllers\Api\CameraFeedController::class, 'store']);
                Route::patch('camera-feeds/{feed}', [\App\Http\Controllers\Api\CameraFeedController::class, 'update']);
                Route::delete('camera-feeds/{feed}', [\App\Http\Controllers\Api\CameraFeedController::class, 'destroy']);
                Route::post('camera-feeds/{feed}/poll', [\App\Http\Controllers\Api\CameraFeedController::class, 'poll']);
                Route::post('camera-feeds/{feed}/frames', [\App\Http\Controllers\Api\CameraFeedController::class, 'upload'])->middleware('throttle:uploads');
            });
        });
    });
});
