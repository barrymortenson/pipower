---
name: PHP Testing with Pest
description: Run PHP tests using Pest framework. Use when running tests, checking test coverage, or writing new tests for Laravel components.
---

# PHP Testing with Pest

## Running Tests

```bash
# All tests
php artisan test

# With coverage report
php artisan test --coverage

# Single test file
php artisan test tests/Feature/Api/HealthTest.php

# Filter by test name
php artisan test --filter="returns 200"
```

## Test Organization

```
tests/
├── Feature/Api/     # API endpoint integration tests
├── Unit/Models/     # Model unit tests
├── Unit/Services/   # Service unit tests
├── Pest.php         # Pest configuration
└── TestCase.php     # Base test case
```

## Writing Tests

Use Pest's functional syntax:

```php
<?php

use App\Services\NutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('describes what the test verifies', function () {
    // Arrange
    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'connected' => true,
            'on_battery' => false,
        ]);
    });

    // Act
    $response = $this->get('/api/health');

    // Assert
    $response->assertStatus(200);
});
```

## Key Patterns

- **Mock external services**: Always mock `NutService` and network commands
- **Use RefreshDatabase**: Include `uses(RefreshDatabase::class)` for database tests
- **Descriptive names**: Use `it('does X when Y')` format
- **Arrange/Act/Assert**: Structure tests clearly

## API Test Assertions

```php
$response->assertStatus(200);
$response->assertJson(['key' => 'value']);
$response->assertJsonStructure(['data' => ['id', 'name']]);
```
