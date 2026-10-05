<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use RuntimeException;
use Dirthara\I18n\Locale;

use function sprintf;

final class TranslationCacheException extends RuntimeException implements I18nException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function unusableDirectory(string $path, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf(
                'The translation cache directory "%s" does not exist and cannot be created.',
                self::printable($path),
            ),
            previous: $previous,
            context: ['path' => $path],
        );
    }

    public static function writeFailed(
        string $path,
        string $cacheKey,
        Locale $locale,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: sprintf(
                'Unable to write the translation cache entry "%s" for locale "%s" to "%s".',
                self::printable($cacheKey),
                $locale->code,
                self::printable($path),
            ),
            previous: $previous,
            context: ['path' => $path, 'cacheKey' => $cacheKey, 'locale' => $locale->code],
        );
    }

    public static function renameFailed(
        string $path,
        string $cacheKey,
        Locale $locale,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: sprintf(
                'Unable to move the translation cache entry "%s" for locale "%s" into place at "%s".',
                self::printable($cacheKey),
                $locale->code,
                self::printable($path),
            ),
            previous: $previous,
            context: ['path' => $path, 'cacheKey' => $cacheKey, 'locale' => $locale->code],
        );
    }

    public static function loadFailed(string $path, string $cacheKey, Locale $locale, Throwable $previous): self
    {
        return new self(
            message: sprintf(
                'Unable to load the translation cache entry "%s" for locale "%s" from "%s".',
                self::printable($cacheKey),
                $locale->code,
                self::printable($path),
            ),
            previous: $previous,
            context: ['path' => $path, 'cacheKey' => $cacheKey, 'locale' => $locale->code],
        );
    }

    public static function malformedEntry(string $path, string $cacheKey, Locale $locale): self
    {
        return new self(
            message: sprintf(
                'The translation cache entry "%s" for locale "%s" at "%s" does not return an array.',
                self::printable($cacheKey),
                $locale->code,
                self::printable($path),
            ),
            context: ['path' => $path, 'cacheKey' => $cacheKey, 'locale' => $locale->code],
        );
    }

    public static function removeFailed(
        string $path,
        string $cacheKey,
        Locale $locale,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: sprintf(
                'Unable to remove the translation cache entry "%s" for locale "%s" at "%s".',
                self::printable($cacheKey),
                $locale->code,
                self::printable($path),
            ),
            previous: $previous,
            context: ['path' => $path, 'cacheKey' => $cacheKey, 'locale' => $locale->code],
        );
    }

    public static function removeAllFailed(string $path, string $cacheKey, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf(
                'Unable to remove every translation cache entry "%s" from "%s".',
                self::printable($cacheKey),
                self::printable($path),
            ),
            previous: $previous,
            context: ['path' => $path, 'cacheKey' => $cacheKey],
        );
    }
}
