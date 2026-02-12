<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private array $allowedSettings = [
        'hostname',
        'static_ip',
        'static_gateway',
        'static_dns_primary',
        'static_dns_secondary',
    ];

    public function __construct(
        private SystemConfigService $systemConfig
    ) {}

    public function index(): JsonResponse
    {
        $settings = [];
        foreach ($this->allowedSettings as $key) {
            $settings[$key] = Setting::get($key);
        }

        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => 'sometimes|string|regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/i|max:63',
            'static_ip' => 'sometimes|nullable|ip',
            'static_gateway' => 'sometimes|nullable|ip',
            'static_dns_primary' => 'sometimes|nullable|ip',
            'static_dns_secondary' => 'sometimes|nullable|ip',
        ]);

        foreach ($validated as $key => $value) {
            if (in_array($key, $this->allowedSettings)) {
                Setting::set($key, $value);
            }
        }

        // Apply hostname change to the system
        if (isset($validated['hostname']) && ! empty($validated['hostname'])) {
            $this->systemConfig->applyHostname($validated['hostname']);
        }

        return $this->index();
    }
}
