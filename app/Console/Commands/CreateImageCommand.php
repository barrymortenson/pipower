<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class CreateImageCommand extends Command
{
    protected $signature = 'power-setup:create-image {--output= : Output path for the image}';

    protected $description = 'Create a distributable SD card image from inserted card';

    public function handle(): int
    {
        if (PHP_OS !== 'Darwin') {
            error('This command only works on macOS.');

            return 1;
        }

        info('UPS Monitor Image Creator');
        note('Insert the SD card from a configured Raspberry Pi.');
        $this->newLine();

        // Check for required tools
        if (! $this->checkTools()) {
            return 1;
        }

        // Find and select the SD card
        $disk = $this->selectDisk();
        if (! $disk) {
            return 1;
        }

        // Determine output path
        $outputPath = $this->option('output') ?? $this->getDefaultOutputPath();

        // Confirm before proceeding
        $this->newLine();
        warning("Will copy {$disk} to {$outputPath}");
        if (! confirm('Proceed with image creation?', true)) {
            info('Cancelled.');

            return 0;
        }

        // Unmount the disk
        info('Unmounting disk...');
        $result = Process::run("diskutil unmountDisk /dev/{$disk}");
        if (! $result->successful()) {
            error('Failed to unmount disk: '.$result->errorOutput());

            return 1;
        }

        // Copy the image
        $rawDisk = str_replace('disk', 'rdisk', $disk);
        $tempPath = str_replace('.img', '-full.img', $outputPath);

        info("Copying image from /dev/{$rawDisk}...");
        note('This may take 10-30 minutes depending on SD card size.');
        $this->newLine();

        $copyCommand = $this->getCopyCommand($rawDisk, $tempPath);
        $result = Process::timeout(3600)->tty()->run($copyCommand);

        if (! $result->successful()) {
            error('Failed to copy image.');

            return 1;
        }

        $this->newLine();
        info('Image copied successfully.');

        // Eject the disk
        Process::run("diskutil eject /dev/{$disk}");
        note('SD card ejected. You can remove it now.');

        // Shrink the image
        if ($this->commandExists('pishrink')) {
            $this->newLine();
            info('Shrinking image with PiShrink...');
            note('This may take a few minutes.');

            // Add Homebrew e2fsprogs to PATH (required for modern ext4 features)
            $e2fsPath = '/opt/homebrew/opt/e2fsprogs/bin:/opt/homebrew/opt/e2fsprogs/sbin';
            $path = $e2fsPath.':'.getenv('PATH');

            $result = Process::timeout(1800)
                ->env(['PATH' => $path])
                ->run("pishrink {$tempPath} {$outputPath}");

            if ($result->successful()) {
                // Remove the full-size image
                unlink($tempPath);
                // Fix image size to be multiple of 512 bytes (sector size)
                $this->padImageToSectorBoundary($outputPath);
                $finalPath = $this->compressImage($outputPath);
                info("Distributable image created: {$finalPath}");
            } else {
                warning('PiShrink failed. Full image saved at: '.$tempPath);
                if ($result->errorOutput()) {
                    $this->error($result->errorOutput());
                }
                if ($result->output()) {
                    $this->line($result->output());
                }
            }
        } else {
            // Just rename if pishrink not available
            rename($tempPath, $outputPath);
            $finalPath = $this->compressImage($outputPath);
            info("Image created: {$finalPath}");
            note('Install pishrink to create smaller distributable images.');
        }

        $this->newLine();
        info('Done! Flash this image to new SD cards using Raspberry Pi Imager.');

        return 0;
    }

    private function compressImage(string $imagePath): string
    {
        $this->newLine();
        info('Compressing image...');
        note('This may take a few minutes.');

        $compressedPath = $imagePath.'.xz';

        // Use xz with good compression (level 6 is a good balance of speed/size)
        $result = Process::timeout(3600)->run("xz -6 -f {$imagePath}");

        if ($result->successful() && file_exists($compressedPath)) {
            $originalSize = 0;
            $compressedSize = filesize($compressedPath);
            $sizeMb = round($compressedSize / 1024 / 1024);
            note("Compressed to {$sizeMb}MB");

            return $compressedPath;
        }

        warning('Compression failed. Uncompressed image saved.');

        return $imagePath;
    }

    private function padImageToSectorBoundary(string $imagePath): void
    {
        $sectorSize = 512;
        $fileSize = filesize($imagePath);
        $remainder = $fileSize % $sectorSize;

        if ($remainder !== 0) {
            $paddingNeeded = $sectorSize - $remainder;
            $handle = fopen($imagePath, 'ab');
            fwrite($handle, str_repeat("\0", $paddingNeeded));
            fclose($handle);
        }
    }

    private function checkTools(): bool
    {
        $hasDcfldd = $this->commandExists('dcfldd');
        $hasPishrink = $this->commandExists('pishrink');

        if (! $hasDcfldd) {
            warning('dcfldd not found. Will use dd (slower, no progress bar).');
            note('Install with: brew install dcfldd');
            $this->newLine();
        }

        if (! $hasPishrink) {
            warning('pishrink not found. Image will not be shrunk.');
            note('See docs/create-distributable-image.md for installation instructions.');
            $this->newLine();
        }

        return true;
    }

    private function selectDisk(): ?string
    {
        $result = Process::run('diskutil list');
        if (! $result->successful()) {
            error('Failed to list disks.');

            return null;
        }

        // Parse diskutil output to find external disks
        $output = $result->output();
        $disks = [];

        preg_match_all('/\/dev\/(disk\d+).*external.*$/m', $output, $matches);

        if (empty($matches[1])) {
            // Try alternative pattern for SD cards
            preg_match_all('/\/dev\/(disk\d+).*\(internal.*physical\).*$/m', $output, $matches);
        }

        // Get disk info for each potential disk
        foreach ($matches[1] ?? [] as $disk) {
            $infoResult = Process::run("diskutil info /dev/{$disk}");
            if ($infoResult->successful()) {
                $info = $infoResult->output();

                // Extract size
                if (preg_match('/Disk Size:\s+([^\n]+)/', $info, $sizeMatch)) {
                    $size = trim($sizeMatch[1]);
                    $disks[$disk] = "{$disk} ({$size})";
                }
            }
        }

        if (empty($disks)) {
            error('No external disks found. Insert an SD card and try again.');

            return null;
        }

        if (count($disks) === 1) {
            $disk = array_key_first($disks);
            info("Found SD card: {$disks[$disk]}");

            return $disk;
        }

        return select(
            label: 'Select the SD card:',
            options: $disks,
        );
    }

    private function getDefaultOutputPath(): string
    {
        $downloads = $_SERVER['HOME'].'/Downloads';

        return "{$downloads}/pipower-dist.img";
    }

    private function getCopyCommand(string $rawDisk, string $outputPath): string
    {
        if ($this->commandExists('dcfldd')) {
            return "sudo dcfldd if=/dev/{$rawDisk} of={$outputPath}";
        }

        return "sudo dd if=/dev/{$rawDisk} of={$outputPath} bs=4m status=progress";
    }

    private function commandExists(string $command): bool
    {
        $result = Process::run("command -v {$command}");

        return $result->successful() && ! empty(trim($result->output()));
    }
}
