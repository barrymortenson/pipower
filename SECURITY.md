# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.x     | Yes                |

## Reporting a Vulnerability

We take security seriously. If you discover a vulnerability, please report it responsibly.

**Do not open a public issue for security vulnerabilities.**

Instead, please use [GitHub's private security advisory feature](https://github.com/barrymortenson/PiPower/security/advisories/new) to report the issue.

### What to Include

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

### Response Timeline

- **Acknowledgment**: Within 48 hours
- **Initial assessment**: Within 1 week
- **Fix timeline**: Depends on severity, but we aim to address critical issues within 30 days

### Scope

The following are in scope:

- PiPower application code (PHP/Laravel backend, Vue.js frontend)
- API endpoints
- Authentication and authorization mechanisms
- Configuration handling

The following are out of scope:

- NUT (Network UPS Tools) itself
- Raspberry Pi OS
- Third-party dependencies (report these to their maintainers directly)

## Security Best Practices for Deployment

- Change the default SSH password (`raspberry`) immediately after first boot
- Use a firewall to restrict access to the PiPower web interface
- Keep the system updated via the web UI or `pa power:deploy`
- Use TLS if exposing PiPower beyond your local network
