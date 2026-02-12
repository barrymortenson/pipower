# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PiPower is a Raspberry Pi-based power monitoring appliance that communicates with UPS devices via NUT (Network UPS Tools) and sends notifications during power events. It's a full-stack Laravel 12 + Vue.js 3 application designed for headless Raspberry Pi deployment.

## Raspberry Pi Defaults

- **Hostname**: power-setup.local
- **SSH**: `pi` / `raspberry`

## Critical Rules

### Git Commits
- **NEVER automatically commit changes**
- **NEVER offer to commit** - do not ask "Would you like me to commit?" or suggest committing
- **Only commit when the user explicitly asks** (e.g., "commit this", "make a commit")

## Development Environment

PHP is installed via Homebrew. Development uses Laravel Valet at http://power.test.

```bash
# Install dependencies
composer install && pnpm install

# Run dev services (Vite HMR, queue worker, log viewer)
composer run dev

# PHP tests
php artisan test
php artisan test --coverage
php artisan test tests/Feature/Api/StatusTest.php
```

### Database

```bash
touch database/database.sqlite && php artisan migrate
```

### Build

```bash
pnpm run build
```

## Architecture

```
app/
├── Console/Commands/     # CLI: power:status, power:health, power:notify-test
├── Http/Controllers/Api/ # REST endpoints (Health, Status, Recipients, Settings, Network)
├── Mail/                 # Email notification templates
├── Models/               # Event, Recipient, Setting
└── Services/
    ├── NutService.php           # UPS communication via upsc command
    ├── PiPowerService.php  # Power state monitoring, event detection
    ├── NotificationService.php  # Email (SMTP) and SMS (Plivo) dispatch
    └── NetworkService.php       # WiFi scanning, network configuration

resources/js/
├── App.vue               # Root component
├── pages/                # Dashboard, Network, Notifications, System, Setup
├── services/api.js       # Axios API client
└── router/index.js       # Vue Router SPA routes

routes/api.php            # All REST endpoints defined here
config/pipower.php   # App-specific config (NUT, notifications, polling)
```

## Key Patterns

- **Services over Controllers**: Business logic lives in `app/Services/`, controllers are thin
- **NUT Integration**: `NutService` shells out to `upsc` command to query UPS status
- **Mocking External Services**: Tests mock `NutService` and network commands to avoid hardware dependencies
- **Vue Composition API**: All components use `<script setup>` syntax
- **Path alias**: `@` maps to `resources/js/` in Vite config

## API Health Semantics

- `GET /api/health` returns **200** when on AC power + UPS connected
- Returns **503** when on battery or UPS disconnected (for uptime monitoring tools)

## Code Standards

- **PHP**: PSR-12, type hints required
- **Vue**: Composition API with `<script setup>`
- **Commits**: Conventional format (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`)

## Skills

Detailed guidelines are in `.claude/skills/`:

| Skill | Purpose |
|-------|---------|
| [PHP Testing](.claude/skills/php-testing/SKILL.md) | Pest test patterns, mocking, assertions |
