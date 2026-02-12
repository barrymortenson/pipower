# Building the PiPower Image

> **Note:** These build instructions have been tested on macOS. Linux users will need to adapt the SD card and disk imaging commands for their platform.

## Step 1: Flash Raspberry Pi OS

1. Flash **Raspberry Pi OS Lite (64-bit)** to a **16GB or larger** SD card using Raspberry Pi Imager
2. Click **Customisation** and configure each section:

| Section | Setting | Value |
|---------|---------|-------|
| **Hostname** | Hostname | `power-setup` |
| **Localisation** | Time zone | `UTC` |
| **Localisation** | Keyboard layout | `us` |
| **User** | Username | `pi` |
| **User** | Password | `raspberry` |
| **Remote access** | Enable SSH | Yes (password authentication) |

> **Note:** Do not enable Raspberry Pi Connect in Imager. Each device needs a unique token, which is configured during deployment via `pa power:deploy`.

3. Click **Write** to flash the image
4. Re-insert the SD card and configure static IP:
   ```bash
   php artisan power-setup:sd-card
   ```

> See [docs/sd-card-manual-setup.md](docs/sd-card-manual-setup.md) for manual setup.

## Step 2: Boot and Connect

1. Insert SD card, connect ethernet, power on
2. Wait ~2 minutes, then SSH:
   ```bash
   ssh pi@192.168.100.40
   ```
3. Run:
   ```bash
   sudo /boot/firmware/pipower-setup.sh
   ```

## Step 3: System Setup

This verifies network connectivity before running updates. 
```bash
ip addr show eth0 | grep "inet " && ping -c 1 google.com && sudo apt update && sudo apt upgrade -y && sudo apt install -y git vim curl wget htop
```

## Step 4: Install PiPower

> **Connect the UPS via USB before running install.**

Then:

```bash
git clone https://github.com/barrymortenson/PiPower.git
cd PiPower
sudo ./install.sh
```

The install script verifies the web server and UPS connection, and cleans up the system automatically.

## Step 5: Create Distributable Image

On the Pi, shutdown:

```bash
sudo shutdown -h now
```

Remove the SD card and insert into your computer, then:

```bash
php artisan power-setup:create-image
```

This command auto-detects the SD card, copies the image, and shrinks it for distribution.

> For manual steps or troubleshooting, see [docs/create-distributable-image.md](docs/create-distributable-image.md).

## Deploying

1. Flash `pipower-dist.img` using Raspberry Pi Imager
2. Connect UPS and ethernet, power on
3. Access the web interface at `http://192.168.100.40`
