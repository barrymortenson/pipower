<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\UsPhoneNumber;
use App\Services\NotificationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function __construct(
        private NotificationSettingsService $settings
    ) {}

    public function show(): JsonResponse
    {
        return response()->json($this->settings->toArray());
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'smtp_host' => 'sometimes|nullable|string|max:255',
            'smtp_port' => 'sometimes|nullable|integer|between:1,65535',
            'smtp_username' => 'sometimes|nullable|string|max:255',
            'smtp_password' => 'sometimes|nullable|string|max:255',
            'clear_smtp_password' => 'sometimes|boolean',
            'smtp_from_address' => 'sometimes|nullable|email|max:255',
            'smtp_from_name' => 'sometimes|nullable|string|max:255',
            'plivo_auth_id' => 'sometimes|nullable|string|max:255',
            'plivo_auth_token' => 'sometimes|nullable|string|max:255',
            'clear_plivo_auth_token' => 'sometimes|boolean',
            'plivo_from_number' => ['sometimes', 'nullable', 'string', new UsPhoneNumber],
        ]);

        $this->settings->update($validated);

        return $this->show();
    }
}
