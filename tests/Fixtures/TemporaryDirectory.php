<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use function chmod;
use function mkdir;
use function rmdir;
use function is_dir;
use function unlink;
use function bin2hex;
use function is_link;
use function scandir;
use function random_bytes;
use function sys_get_temp_dir;

final readonly class TemporaryDirectory
{
    public string $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . '/dirthara-i18n-' . bin2hex(random_bytes(8));

        mkdir($this->path);
    }

    /**
     * @return list<string>
     */
    public function files(?string $directory = null): array
    {
        $files = [];

        $names = scandir($directory ?? $this->path);

        foreach ($names === false ? [] : $names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $files[] = $name;
        }

        return $files;
    }

    public function remove(): void
    {
        $this->removeDirectory($this->path);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        chmod($directory, permissions: 0o755);

        foreach ($this->files($directory) as $name) {
            $path = $directory . '/' . $name;

            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);

                continue;
            }

            chmod($path, permissions: 0o644);
            unlink($path);
        }

        rmdir($directory);
    }
}
