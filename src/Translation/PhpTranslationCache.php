<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Closure;
use Throwable;
use ErrorException;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\TranslationCache;
use Dirthara\I18n\Exception\TranslationCacheException;

use function hash;
use function ksort;
use function mkdir;
use function is_dir;
use function rename;
use function strlen;
use function unlink;
use function bin2hex;
use function is_file;
use function is_array;
use function is_string;
use function var_export;
use function random_bytes;
use function file_put_contents;
use function set_error_handler;
use function restore_error_handler;

use const SORT_STRING;

/**
 * Stores each catalogue as a PHP file in a local directory, so a hit is a plain include that OPcache can serve.
 *
 * An entry is written to a temporary file in the same directory and renamed into place, which is atomic on one
 * filesystem: a concurrent request sees the previous entry or the new one, never part of one. The file name is a hash
 * of the cache key, so no key can reach outside the directory.
 */
final readonly class PhpTranslationCache implements TranslationCache
{
    /**
     * @throws TranslationCacheException
     */
    public function __construct(
        private string $path,
    ) {
        if ($path === '') {
            throw TranslationCacheException::unusableDirectory($path);
        }
    }

    /**
     * @throws TranslationCacheException
     */
    public function get(string $key, Locale $locale): ?TranslationCatalogue
    {
        $file = $this->file($key, $locale);

        if (!is_file($file)) {
            return null;
        }

        // Include rather than require: an entry forgotten after the check above makes require a fatal error, while
        // include only warns, which is reported below.
        try {
            // @mago-expect analysis:mixed-assignment
            [$messages, $error] = $this->attempt(static fn(): mixed => include $file);
        } catch (Throwable $exception) {
            throw TranslationCacheException::loadFailed($file, $key, $locale, previous: $exception);
        }

        if ($error !== null) {
            throw TranslationCacheException::loadFailed($file, $key, $locale, previous: $error);
        }

        if (!is_array($messages)) {
            throw TranslationCacheException::malformedEntry($file, $key, $locale);
        }

        // @mago-expect analysis:mixed-assignment
        foreach ($messages as $message) {
            if (!is_string($message)) {
                throw TranslationCacheException::malformedEntry($file, $key, $locale);
            }
        }

        return new TranslationCatalogue($locale, $messages);
    }

    /**
     * @throws TranslationCacheException
     */
    public function put(string $key, TranslationCatalogue $catalogue): void
    {
        $this->createDirectory();

        $file = $this->file($key, $catalogue->locale);
        $temporary = $file . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $contents = $this->compile($catalogue->messages);

        [$written, $error] = $this->attempt(static fn(): int|false => file_put_contents($temporary, $contents));

        if ($written !== strlen($contents)) {
            $this->attempt(static fn(): bool => unlink($temporary));

            throw TranslationCacheException::writeFailed($file, $key, $catalogue->locale, previous: $error);
        }

        [$renamed, $error] = $this->attempt(static fn(): bool => rename($temporary, $file));

        if (!$renamed) {
            $this->attempt(static fn(): bool => unlink($temporary));

            throw TranslationCacheException::renameFailed($file, $key, $catalogue->locale, previous: $error);
        }
    }

    /**
     * @throws TranslationCacheException
     */
    public function forget(string $key, Locale $locale): void
    {
        $file = $this->file($key, $locale);

        if (!is_file($file)) {
            return;
        }

        [$removed, $error] = $this->attempt(static fn(): bool => unlink($file));

        if (!$removed && is_file($file)) {
            throw TranslationCacheException::removeFailed($file, $key, $locale, previous: $error);
        }
    }

    private function file(string $key, Locale $locale): string
    {
        return $this->path . '/' . hash('sha256', $key) . '.' . $locale->code . '.php';
    }

    /**
     * @throws TranslationCacheException
     */
    private function createDirectory(): void
    {
        if (is_dir($this->path)) {
            return;
        }

        [$created, $error] = $this->attempt(fn(): bool => mkdir($this->path, permissions: 0o777, recursive: true));

        if (!$created && !is_dir($this->path)) {
            throw TranslationCacheException::unusableDirectory($this->path, previous: $error);
        }
    }

    /**
     * @param array<array-key, string> $messages
     */
    private function compile(array $messages): string
    {
        ksort($messages, SORT_STRING);

        return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($messages, return: true) . ";\n";
    }

    /**
     * Runs a filesystem function with its warning captured, so a failure can be reported as an exception instead.
     *
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return array{T, ?ErrorException}
     */
    private function attempt(Closure $operation): array
    {
        $error = null;

        set_error_handler(static function (int $severity, string $message, string $file, int $line) use (
            &$error,
        ): bool {
            $error = new ErrorException($message, 0, $severity, $file, $line);

            return true;
        });

        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }

        return [$result, $error];
    }
}
