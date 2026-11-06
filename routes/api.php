<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Http\Controllers\Api\PiConnectController;
use App\Http\Controllers\Api\RecipientsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

// Public endpoints - safe for external monitoring tools
Route::get('/ping', fn () => response()->json(['ok' => true]));
Route::get('/health', HealthController::class);

// Protected endpoints - require API token
Route::middleware('api.token')->group(function () {
    Route::get('/status', StatusController::class);

    Route::apiResource('recipients', RecipientsController::class);
    Route::post('/notifications/test', function () {
        $service = app(\App\Services\NotificationService::class);

        return response()->json($service->sendTestNotification());
    });

    Route::get('/settings/notifications', [NotificationSettingsController::class, 'show']);
    Route::put('/settings/notifications', [NotificationSettingsController::class, 'update']);

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);

    Route::get('/events', [App\Http\Controllers\Api\EventsController::class, 'index']);

    Route::get('/network/status', [App\Http\Controllers\Api\NetworkController::class, 'status']);
    Route::post('/network/apply-and-reboot', [App\Http\Controllers\Api\NetworkController::class, 'applyAndReboot']);

    Route::get('/update/check', [App\Http\Controllers\Api\UpdateController::class, 'check']);
    Route::get('/update/status', [App\Http\Controllers\Api\UpdateController::class, 'status']);
    Route::post('/update/apply', [App\Http\Controllers\Api\UpdateController::class, 'apply']);

    Route::post('/system/reboot', function () {
        if (PHP_OS_FAMILY !== 'Linux') {
            return response()->json(['success' => false, 'message' => 'Only available on Raspberry Pi']);
        }
        \Illuminate\Support\Facades\Process::path('/tmp')->run('nohup bash -c "sleep 2; sudo /sbin/reboot" &>/dev/null &');

        return response()->json(['success' => true]);
    });

    Route::post('/system/shutdown', function () {
        if (PHP_OS_FAMILY !== 'Linux') {
            return response()->json(['success' => false, 'message' => 'Only available on Raspberry Pi']);
        }
        \Illuminate\Support\Facades\Process::path('/tmp')->run('nohup bash -c "sleep 2; sudo /sbin/shutdown -h now" &>/dev/null &');

        return response()->json(['success' => true]);
    });

    Route::get('/pi-connect/status', [PiConnectController::class, 'status']);
    Route::post('/pi-connect/signin', [PiConnectController::class, 'signin']);
    Route::post('/pi-connect/signout', [PiConnectController::class, 'signout']);
});
