# SD Card Manual Setup

Manual instructions for configuring static IP on an SD card. For automated setup, use the `php artisan pipower:setup-sd` command instead.

## Prerequisites

1. Flash Raspberry Pi OS using Raspberry Pi Imager with:
   - **Hostname**: `power-setup`
   - **Username**: `pi`
   - **Password**: `raspberry`
   - **SSH**: Enabled

2. Mount the boot partition after flashing:
   - **macOS**: Mounts automatically as `/Volumes/bootfs`
   - **Linux**: Mount to a known path (e.g., `/mnt/bootfs`)

## Configure Static IP

### 1. Edit cmdline.txt

Edit `cmdline.txt` on the boot partition. Insert the `ip=` parameter **after `rootwait`**, keeping everything on a single line.

```bash
# Open the file
vim /Volumes/bootfs/cmdline.txt  # macOS
vim /mnt/bootfs/cmdline.txt      # Linux
```

**Before:**
```
console=serial0,115200 console=tty1 root=PARTUUID=... rootfstype=ext4 fsck.repair=yes rootwait cfg80211.ieee80211_regdom=UM
```

**After** (adjust IP/netmask for your network):
```
console=serial0,115200 console=tty1 root=PARTUUID=... rootfstype=ext4 fsck.repair=yes rootwait ip=192.168.100.40:::255.255.254.0:rpi:eth0:off cfg80211.ieee80211_regdom=UM
```

**IP Parameter Format:**
```
ip=<client-ip>:<server-ip>:<gateway>:<netmask>:<hostname>:<interface>:<autoconf>
```

| Field | Description | Example |
|-------|-------------|---------|
| client-ip | Static IP address | `192.168.100.40` |
| server-ip | NFS server (leave empty) | |
| gateway | Gateway IP (leave empty for auto) | |
| netmask | Subnet mask | `255.255.254.0` |
| hostname | Device hostname | `rpi` |
| interface | Network interface | `eth0` |
| autoconf | Auto-configuration | `off` |

### 2. Create DNS Setup Script

Create `pipower-setup.sh` on the boot partition:

```bash
cat > /Volumes/bootfs/pipower-setup.sh << 'EOF'
#!/bin/bash
set -e

echo "Configuring static IP with DNS..."

cat > /etc/NetworkManager/system-connections/ethernet-static.nmconnection << 'NMCONF'
[connection]
id=ethernet-static
type=ethernet
interface-name=eth0
autoconnect=true

[ethernet]

[ipv4]
method=manual
addresses=192.168.100.40/23
gateway=192.168.100.1
dns=8.8.8.8;8.8.4.4
dns-search=

[ipv6]
method=disabled
NMCONF

chmod 600 /etc/NetworkManager/system-connections/ethernet-static.nmconnection
rm -f /etc/NetworkManager/system-connections/preconfigured.nmconnection 2>/dev/null || true
nmcli connection reload
nmcli connection up ethernet-static

echo "Network configured!"
EOF

chmod +x /Volumes/bootfs/pipower-setup.sh
```

> **Note:** Adjust the IP address and prefix length (`/23` = `255.255.254.0`) to match your network.

## Verification

Before ejecting, verify the configuration:

```bash
# Check cmdline.txt has IP parameter
grep "ip=" /Volumes/bootfs/cmdline.txt

# Check setup script exists and is executable
ls -la /Volumes/bootfs/pipower-setup.sh
```

## Next Steps

1. Safely eject the SD card
2. Insert into Raspberry Pi
3. Connect ethernet cable
4. Power on and wait ~2 minutes for first boot
5. SSH into the Pi:

```bash
ssh pi@192.168.100.40
```

6. Run the setup script to configure DNS:

```bash
sudo /boot/firmware/pipower-setup.sh
```
