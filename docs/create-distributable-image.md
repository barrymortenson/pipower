# Creating a Distributable Image (Manual Steps)

This document details the manual process for creating a distributable PiPower SD card image. For automated creation, use `php artisan power-setup:create-image`.

## Prerequisites

### On the Raspberry Pi

Before removing the SD card, clean up and shutdown:

```bash
sudo apt-get autoremove -y
sudo apt-get autoclean
sudo shutdown -h now
```

Wait for the Pi to fully shut down, then remove the SD card and insert it into your Mac.

### On macOS (one-time setup)

Install the required tools:

```bash
# Install dcfldd (faster than dd with progress)
brew install dcfldd

# Install PiShrink for macOS
if ! command -v pishrink &> /dev/null; then
    curl -LO https://github.com/lisanet/PiShrink-macOS/archive/master.zip
    unzip master.zip && cd PiShrink-macOS-master
    make && sudo make install
    cd .. && rm -rf master.zip PiShrink-macOS-master
fi
```

## Copy Image from SD Card

1. **Find the SD card disk:**

   ```bash
   diskutil list
   ```

   Look for the disk matching your SD card size (e.g., `disk4`).

2. **Unmount the disk:**

   ```bash
   diskutil unmountDisk /dev/diskN  # Replace N with your disk number
   ```

3. **Copy the image:**

   Using dcfldd (faster, with progress):
   ```bash
   sudo dcfldd if=/dev/rdiskN of=~/Downloads/pipower.img
   ```

   Or using standard dd:
   ```bash
   sudo dd if=/dev/rdiskN of=~/Downloads/pipower.img bs=4m status=progress
   ```

   > **Note:** Use `/dev/rdiskN` (raw disk) instead of `/dev/diskN` for 2-3x faster transfers.

4. **Eject the SD card:**

   ```bash
   diskutil eject /dev/diskN
   ```

## Shrink the Image

PiShrink removes empty space from the image, making it smaller and faster to flash:

```bash
pishrink ~/Downloads/pipower.img ~/Downloads/pipower-dist.img
```

The resulting `pipower-dist.img` file is ready for distribution.

## Flash to New SD Cards

Use Raspberry Pi Imager or Balena Etcher to flash `pipower-dist.img` to new SD cards.
