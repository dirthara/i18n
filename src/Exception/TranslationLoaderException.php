<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use JsonException;
use RuntimeException;
use Dirthara\I18n\Locale;
use League\Flysystem\FilesystemException;

use function sprintf;

final class TranslationLoaderException extends RuntimeException implements I18nException
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

    public static function missingSource(string $path): self
    {
        return new self(
            message: sprintf('The translation source "%s" does not exist.', self::printable($path)),
            context: ['path' => $path],
        );
    }

    public static function unreadableSource(string $path, FilesystemException $previous): self
    {
        return new self(
            message: sprintf('Unable to read the translation source "%s".', self::printable($path)),
            previous: $previous,
            context: ['path' => $path],
        );
    }

    public static function phpSourceFailed(string $path, Throwable $previous): self
    {
        return new self(
            message: sprintf('The translation file "%s" failed while loading.', self::printable($path)),
            previous: $previous,
            context: ['path' => $path],
        );
    }

    public static function invalidPhpReturn(string $path): self
    {
        return new self(
            message: sprintf('The translation file "%s" must return an array.', self::printable($path)),
            context: ['path' => $path],
        );
    }

    public static function malformedJson(string $path, JsonException $previous): self
    {
        return new self(
            message: sprintf('The translation file "%s" does not contain valid JSON.', self::printable($path)),
            previous: $previous,
            context: ['path' => $path],
        );
    }

    public static function invalidJsonDocument(string $path): self
    {
        return new self(
            message: sprintf('The translation file "%s" must contain a JSON object.', self::printable($path)),
            context: ['path' => $path],
        );
    }

    public static function invalidTranslation(string $path, string $key): self
    {
        return new self(
            message: sprintf(
                'The translation "%s" in "%s" is not a string or a group of translations.',
                self::printable($key),
                self::printable($path),
            ),
            context: ['path' => $path, 'key' => $key],
        );
    }

    public static function invalidKey(string $path, string $key): self
    {
        return new self(
            message: sprintf(
                'The translation key "%s" in "%s" is not valid: a key is a string that is not empty and not a decimal '
                . 'integer.',
                self::printable($key),
                self::printable($path),
            ),
            context: ['path' => $path, 'key' => $key],
        );
    }

    public static function duplicateKey(string $path, string $key): self
    {
        return new self(
            message: sprintf(
                'The translation key "%s" from "%s" is already defined.',
                self::printable($key),
                self::printable($path),
            ),
            context: ['path' => $path, 'key' => $key],
        );
    }

    public static function schemeAlreadyRegistered(string $scheme): self
    {
        return new self(
            message: sprintf(
                'Unable to load PHP translations: another stream wrapper is already registered for the "%s://" scheme.',
                self::printable($scheme),
            ),
            context: ['scheme' => $scheme],
        );
    }

    public static function registrationFailed(string $scheme): self
    {
        return new self(
            message: sprintf(
                'Unable to load PHP translations: the stream wrapper for the "%s://" scheme could not be registered.',
                self::printable($scheme),
            ),
            context: ['scheme' => $scheme],
        );
    }

    public static function conflictingKey(Locale $locale, string $key, int|string $first, int|string $second): self
    {
        return new self(
            message: sprintf(
                'The translation key "%s" for locale "%s" is defined by both loader %s and loader %s.',
                self::printable($key),
                $locale->code,
                self::printable((string) $first),
                self::printable((string) $second),
            ),
            context: ['locale' => $locale->code, 'key' => $key, 'loaders' => [$first, $second]],
        );
    }

    public static function localeMismatch(Locale $requested, Locale $loaded): self
    {
        return new self(
            message: sprintf(
                'A translation loader returned a catalogue for locale "%s" when "%s" was requested.',
                $loaded->code,
                $requested->code,
            ),
            context: ['locale' => $requested->code, 'loadedLocale' => $loaded->code],
        );
    }
}
