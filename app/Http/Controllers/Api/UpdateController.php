<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Process;

class UpdateController extends Controller
{
    public function check(): JsonResponse
    {
        $repoDir = '/home/pi/pipower';

        // Check if repo exists
        if (! is_dir($repoDir.'/.git')) {
            return response()->json([
                'current_version' => 'Unknown',
                'update_available' => false,
                'error' => 'Git repository not found at '.$repoDir,
            ]);
        }

        // Get current version
        $currentResult = Process::path($repoDir)->run('git rev-parse --short HEAD');
        if (! $currentResult->successful()) {
            return response()->json([
                'current_version' => 'Unknown',
                'update_available' => false,
                'error' => 'Failed to read git repository',
            ]);
        }
        $currentVersion = trim($currentResult->output());

        // Fetch latest from remote as pi user (has SSH keys)
        Process::path('/tmp')->run('sudo -u pi git -C '.$repoDir.' fetch origin 2>/dev/null');

        // Get remote version
        $remoteResult = Process::path($repoDir)->run('git rev-parse --short origin/main');
        $remoteVersion = $remoteResult->successful() ? trim($remoteResult->output()) : $currentVersion;

        // Get commit count behind
        $behindResult = Process::path($repoDir)->run('git rev-list HEAD..origin/main --count');
        $commitsBehind = $behindResult->successful() ? (int) trim($behindResult->output()) : 0;

        // Get current version tag if exists
        $tagResult = Process::path($repoDir)->run('git describe --tags --always 2>/dev/null || echo ""');
        $versionTag = trim($tagResult->output()) ?: $currentVersion;

        return response()->json([
            'current_version' => $versionTag,
            'current_commit' => $currentVersion,
            'latest_commit' => $remoteVersion,
            'update_available' => $commitsBehind > 0,
            'commits_behind' => $commitsBehind,
        ]);
    }

    public function apply(): JsonResponse
    {
        $scriptPath = '/home/pi/pipower/update.sh';

        if (! file_exists($scriptPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Update script not found',
            ], 404);
        }

        // Run update script in background (it restarts PHP-FPM which would kill this request)
        Process::path('/tmp')->run("nohup sudo {$scriptPath} > /tmp/pipower-update.log 2>&1 &");

        return response()->json([
            'success' => true,
            'message' => 'Update started. The page will reload automatically.',
        ]);
    }

    public function status(): JsonResponse
    {
        $lockFile = '/tmp/pipower-updating.lock';
        $updating = file_exists($lockFile);

        return response()->json([
            'updating' => $updating,
        ]);
    }
}
