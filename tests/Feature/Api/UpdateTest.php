<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

it('reports an error, not "up to date", when the repo is missing', function () {
    config(['pipower.repo_dir' => '/nonexistent/PiPower']);

    $this->getJson('/api/update/check')
        ->assertOk()
        ->assertJson([
            'current_version' => 'Unknown',
            'update_available' => false,
            'error' => 'Git repository not found at /nonexistent/PiPower',
        ]);
});

it('runs git in the configured repo path', function () {
    Process::fake();

    $repo = sys_get_temp_dir().'/pipower-repo-'.uniqid();
    mkdir("{$repo}/.git", 0777, true);
    config(['pipower.repo_dir' => $repo]);

    $this->getJson('/api/update/check')->assertOk();

    Process::assertRan(fn ($process) => $process->path === $repo
        && $process->command === 'git rev-parse --short HEAD');

    rmdir("{$repo}/.git");
    rmdir($repo);
});

it('returns 404 when update.sh is missing from the configured repo', function () {
    config(['pipower.repo_dir' => '/nonexistent/PiPower']);

    $this->postJson('/api/update/apply')
        ->assertNotFound()
        ->assertJson(['success' => false]);
});
