# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-01

### Added

- Real-time UPS monitoring via NUT (Network UPS Tools)
- Web dashboard with battery level, load percentage, and power status
- Power event detection and logging (power loss, restoration, low battery, critical runtime)
- Email notifications via SMTP
- SMS notifications via Plivo
- Health check endpoint (`/api/health`) returning 200/503 for uptime monitoring integration
- Full REST API for status, events, recipients, settings, and network configuration
- Raspberry Pi Connect integration for remote shell access
- Self-update mechanism via web UI and CLI
- System management (reboot, shutdown) via API
- WiFi network scanning and configuration
- CLI commands: `power:status`, `power:health`, `power:notify-test`, `power:deploy`
- Vue.js 3 SPA with pages for Dashboard, Network, Notifications, System, and Setup
- SQLite database for events, recipients, and settings
- Automated deployment script for Raspberry Pi
