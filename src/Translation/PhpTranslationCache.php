<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Closure;
use Throwable;
use ErrorException;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\TranslationCache;
use Dirthara\I18n\Enum\CacheEntryValidation;
use Dirthara\I18n\Exception\TranslationCacheException;
use Dirthara\I18n\Exception\InvalidTranslationCatalogueException;

use function hash;
use function ksort;
use function mkdir;
use function is_dir;
use function rename;
use function strlen;
use function unlink;
use function bin2hex;
use function is_file;
use function scandir;
use function is_array;
use function preg_match;
use function var_export;
use function random_bytes;
use function file_put_contents;
use function set_error_handler;
use function restore_error_handler;

use const SORT_STRING;

final readonly class PhpTranslationCache implements TranslationCache
{
    /**
     * @throws TranslationCacheException
     */
    public function __construct(
        private string $path,
        private CacheEntryValidation $validation = CacheEntryValidation::Validate,
    ) {
        if ($path === '') {
            throw TranslationCacheException::unusableDirectory($path);
        }
    }

    /**
     * @throws TranslationCacheException
     * @throws InvalidTranslationCatalogueException
     */
    public function get(string $cacheKey, Locale $locale): ?TranslationCatalogue
    {
        $file = $this->file($cacheKey, $locale);

        if (!is_file($file)) {
            return null;
        }

        // Include rather than require: an entry forgotten after the check above makes require a fatal error, while
        // include only warns, which is reported below.
        try {
            // @mago-expect analysis:mixed-assignment
            [$messages, $error] = $this->attempt(static fn(): mixed => include $file);
        } catch (Throwable $exception) {
            throw TranslationCacheException::loadFailed($file, $cacheKey, $locale, previous: $exception);
        }

        if ($error !== null) {
            throw TranslationCacheException::loadFailed($file, $cacheKey, $locale, previous: $error);
        }

        if (!is_array($messages)) {
            throw TranslationCacheException::malformedEntry($file, $cacheKey, $locale);
        }

        if ($this->validation === CacheEntryValidation::Trust) {
            /** @var array<string, string> $messages */
            return TranslationCatalogue::trusted($locale, $messages);
        }

        try {
            return new TranslationCatalogue($locale, $messages);
        } catch (InvalidTranslationCatalogueException $exception) {
            throw $exception->addContext(['path' => $file, 'cacheKey' => $cacheKey]);
        }
    }

    /**
     * @throws TranslationCacheException
     */
    public function put(string $cacheKey, TranslationCatalogue $catalogue): void
    {
        $this->createDirectory();

        $file = $this->file($cacheKey, $catalogue->locale);
        $temporary = $file . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $contents = $this->compile($catalogue->messages);

        [$written, $error] = $this->attempt(static fn(): int|false => file_put_contents($temporary, $contents));

        if ($written !== strlen($contents)) {
            $this->attempt(static fn(): bool => unlink($temporary));

            throw TranslationCacheException::writeFailed($file, $cacheKey, $catalogue->locale, previous: $error);
        }

        [$renamed, $error] = $this->attempt(static fn(): bool => rename($temporary, $file));

        if (!$renamed) {
            $this->attempt(static fn(): bool => unlink($temporary));

            throw TranslationCacheException::renameFailed($file, $cacheKey, $catalogue->locale, previous: $error);
        }
    }

    /**
     * @throws TranslationCacheException
     */
    public function forget(string $cacheKey, Locale $locale): void
    {
        $file = $this->file($cacheKey, $locale);

        if (!is_file($file)) {
            return;
        }

        [$removed, $error] = $this->attempt(static fn(): bool => unlink($file));

        if (!$removed && is_file($file)) {
            throw TranslationCacheException::removeFailed($file, $cacheKey, $locale, previous: $error);
        }
    }

    /**
     * @throws TranslationCacheException
     */
    public function forgetAll(string $cacheKey): void
    {
        if (!is_dir($this->path)) {
            return;
        }

        [$names, $error] = $this->attempt(fn(): array|false => scandir($this->path));

        if ($names === false) {
            throw TranslationCacheException::removeAllFailed($this->path, $cacheKey, previous: $error);
        }

        $entry = '/^' . hash('sha256', $cacheKey) . '\.[A-Za-z0-9-]+\.php\z/';

        foreach ($names as $name) {
            if (preg_match($entry, $name) !== 1) {
                continue;
            }

            $file = $this->path . '/' . $name;

            [$removed, $error] = $this->attempt(static fn(): bool => unlink($file));

            if (!$removed && is_file($file)) {
                throw TranslationCacheException::removeAllFailed($file, $cacheKey, previous: $error);
            }
        }
    }

    private function file(string $cacheKey, Locale $locale): string
    {
        return $this->path . '/' . hash('sha256', $cacheKey) . '.' . $locale->code . '.php';
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
