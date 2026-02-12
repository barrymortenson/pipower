<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class PiConnectController extends Controller
{
    public function status(): JsonResponse
    {
        // Check if rpi-connect is installed
        $installed = $this->isInstalled();

        if (! $installed) {
            return response()->json([
                'installed' => false,
                'signed_in' => false,
            ]);
        }

        // Check if signed in by running rpi-connect status
        $signedIn = $this->isSignedIn();

        return response()->json([
            'installed' => true,
            'signed_in' => $signedIn,
        ]);
    }

    public function signin(Request $request): JsonResponse
    {
        // Only run on Linux (Raspberry Pi)
        if (PHP_OS_FAMILY !== 'Linux') {
            return response()->json([
                'error' => 'Only available on Raspberry Pi',
            ], 400);
        }

        if (! $this->isInstalled()) {
            return response()->json([
                'error' => 'Raspberry Pi Connect is not installed',
            ], 400);
        }

        $authKey = $request->input('auth_key');

        if (empty($authKey)) {
            return response()->json([
                'error' => 'Auth key is required',
            ], 400);
        }

        // Validate auth key format (rpoak_...)
        if (! preg_match('/^rpoak_[a-zA-Z0-9]+$/', $authKey)) {
            return response()->json([
                'error' => 'Invalid auth key format',
            ], 400);
        }

        // Enable user lingering so Connect runs without active login session
        Process::path('/tmp')->run('sudo /usr/bin/loginctl enable-linger pi');

        // Environment variables required for systemd user services when running via sudo
        // XDG_RUNTIME_DIR: Points to user's runtime directory
        // DBUS_SESSION_BUS_ADDRESS: Required for D-Bus communication with user's systemd instance
        $env = 'XDG_RUNTIME_DIR=/run/user/1000 DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1000/bus';

        // Enable the service first (must be done as pi user with session environment)
        Process::path('/tmp')->run("sudo -u pi {$env} /usr/bin/rpi-connect on");

        // Wait for service to start (up to 15 seconds)
        $ready = false;
        for ($i = 0; $i < 15; $i++) {
            sleep(1);
            $statusResult = Process::path('/tmp')->run("sudo -u pi {$env} /usr/bin/rpi-connect status 2>&1");
            $output = $statusResult->output().$statusResult->errorOutput();

            // Service is ready if output doesn't contain "not running"
            if (! str_contains(strtolower($output), 'not running')) {
                $ready = true;
                break;
            }
        }

        if (! $ready) {
            return response()->json([
                'error' => 'Raspberry Pi Connect service did not start',
            ], 500);
        }

        // Sign in with auth key (must run as pi user with session environment)
        $result = Process::path('/tmp')
            ->timeout(30)
            ->run("sudo -u pi {$env} /usr/bin/rpi-connect signin --auth-key={$authKey} 2>&1");

        if ($result->successful()) {
            return response()->json([
                'success' => true,
            ]);
        }

        $output = $result->output();

        // Check if already signed in
        if (str_contains($output, 'already signed in') || $this->isSignedIn()) {
            return response()->json([
                'error' => 'Already signed in to Raspberry Pi Connect',
                'already_signed_in' => true,
            ], 400);
        }

        return response()->json([
            'error' => 'Failed to sign in',
            'details' => $output,
        ], 500);
    }

    public function signout(): JsonResponse
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return response()->json([
                'error' => 'Only available on Raspberry Pi',
            ], 400);
        }

        $env = 'XDG_RUNTIME_DIR=/run/user/1000 DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1000/bus';
        $result = Process::path('/tmp')
            ->timeout(30)
            ->run("sudo -u pi {$env} /usr/bin/rpi-connect signout 2>&1");

        $output = strtolower($result->output());

        // rpi-connect signout returns non-zero exit code even on success
        if ($result->successful() || str_contains($output, 'signed out')) {
            return response()->json([
                'success' => true,
            ]);
        }

        return response()->json([
            'error' => 'Failed to sign out',
            'details' => $result->output(),
        ], 500);
    }

    private function isInstalled(): bool
    {
        $result = Process::path('/tmp')->run('which rpi-connect');

        return $result->successful() && ! empty(trim($result->output()));
    }

    private function isSignedIn(): bool
    {
        $env = 'XDG_RUNTIME_DIR=/run/user/1000 DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1000/bus';
        $result = Process::path('/tmp')
            ->timeout(10)
            ->run("sudo -u pi {$env} /usr/bin/rpi-connect status 2>&1");

        $output = $result->output();

        // Check for indicators of being signed in
        // "Signed in: no" means NOT signed in, so we check for explicit yes
        if (str_contains($output, 'Signed in: no') || str_contains($output, 'not signed in')) {
            return false;
        }

        return str_contains($output, 'Signed in: yes') ||
               str_contains($output, 'Signed in:yes') ||
               str_contains($output, 'Screen sharing:');
    }
}
