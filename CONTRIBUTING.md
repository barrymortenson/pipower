# Contributing to PiPower

Thank you for your interest in contributing to PiPower! This document provides guidelines for contributing to the project.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Set up the development environment (see README.md)
4. Create a feature branch from `main`

## Development Setup

```bash
composer install && pnpm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
composer run dev
# If using Laravel Valet (macOS): http://power.test
# Otherwise, in a separate terminal: php artisan serve → http://localhost:8000
```

## Code Standards

### PHP

- Follow PSR-12 coding standards
- Use type hints for parameters and return types
- Write tests for new features using Pest
- Keep controllers thin, put business logic in services

### JavaScript/Vue

- Use Vue 3 Composition API with `<script setup>`
- Follow Vue.js style guide recommendations
- Use TypeScript for complex components (optional)

### Git Commits

Use conventional commit messages:

- `feat:` New features
- `fix:` Bug fixes
- `docs:` Documentation changes
- `test:` Adding or updating tests
- `refactor:` Code changes that neither fix bugs nor add features
- `chore:` Maintenance tasks

Examples:
```
feat: add battery temperature monitoring
fix: correct notification delay calculation
docs: update API endpoint documentation
test: add tests for NetworkService
```

## Testing

### Running Tests

```bash
php artisan test
php artisan test --coverage
php artisan test tests/Feature/Api/StatusTest.php
```

### Writing Tests

- Place unit tests in `tests/Unit/`
- Place feature/integration tests in `tests/Feature/`
- Use descriptive test names: `it does something when condition`
- Mock external services (NUT, network commands)

Example test:
```php
it('detects power loss event', function () {
    // Arrange
    $nutService = mock(NutService::class);
    $nutService->shouldReceive('getStatus')->andReturn([
        'on_battery' => true,
        'battery_low' => false,
    ]);

    // Act
    $service = new PiPowerService($nutService, ...);
    $result = $service->check();

    // Assert
    expect($result)->toHaveKey('event', 'power_lost');
});
```

## Pull Request Process

1. Ensure all tests pass locally
2. Update documentation if needed
3. Create a pull request with a clear description
4. Link any related issues
5. Wait for review

### PR Description Template

```markdown
## Summary
Brief description of changes

## Changes
- Change 1
- Change 2

## Testing
How to test these changes

## Related Issues
Fixes #123
```

## Reporting Issues

When reporting issues, please include:

- PiPower version
- Raspberry Pi model
- UPS model and connection type
- Steps to reproduce
- Expected vs actual behavior
- Relevant logs (`journalctl -u pipower`)

## Feature Requests

Feature requests are welcome! Please:

1. Check existing issues first
2. Describe the use case
3. Explain the expected behavior
4. Consider implementation complexity

## Code of Conduct

- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn and grow

## Questions?

Open a GitHub issue with the `question` label.
