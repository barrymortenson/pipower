<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            Setting::set('api_token', 'test-token');
            $this->withHeader('X-API-Token', 'test-token');
        } catch (\Exception) {
            // Table may not exist in tests without RefreshDatabase
        }
    }
}
