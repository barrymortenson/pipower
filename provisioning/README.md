# PiPower Provisioning

Scripts and configuration for deploying PiPower on a Raspberry Pi.

## Quick Start

1. Flash Raspberry Pi OS Lite (64-bit) to an SD card
2. Enable SSH and configure initial network access
3. Copy the PiPower source to the Pi
4. Run the installation script:

```bash
cd /path/to/pipower/provisioning
sudo ./install.sh
```

## Files

| File | Description |
|------|-------------|
| `install.sh` | Main installation script |
| `pipower.service` | Systemd service for web app |
| `pipower-scheduler.service` | Systemd service for scheduler |
| `config/ups.conf.example` | NUT configuration examples |

## Services

After installation, these systemd services are available:

```bash
# Start/stop the web application
sudo systemctl start pipower
sudo systemctl stop pipower

# Start/stop the scheduler (power monitoring)
sudo systemctl start pipower-scheduler
sudo systemctl stop pipower-scheduler

# View logs
sudo journalctl -u pipower -f
sudo journalctl -u pipower-scheduler -f
```

## NUT Configuration

Edit `/etc/nut/ups.conf` to match your UPS. See `config/ups.conf.example` for common configurations.

After editing, restart NUT:

```bash
sudo systemctl restart nut-server
```

Test UPS communication:

```bash
upsc ups
```

## Requirements

- Raspberry Pi 3/4/5 (or Zero 2 W)
- Raspberry Pi OS Lite (64-bit recommended)
- USB-connected UPS with NUT support
- Ethernet connection

## Troubleshooting

### UPS not detected

1. Check USB connection: `lsusb`
2. Check NUT driver logs: `sudo journalctl -u nut-server`
3. Try specifying vendor ID in ups.conf

### Web interface not accessible

1. Check service status: `sudo systemctl status pipower`
2. Check logs: `sudo journalctl -u pipower -f`
3. Verify port 80 is not in use: `sudo ss -tlnp | grep :80`
