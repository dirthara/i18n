<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use Dirthara\I18n\Locale;
use InvalidArgumentException;

use function sprintf;

final class InvalidTranslationCatalogueException extends InvalidArgumentException implements I18nException
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

    public static function invalidKey(Locale $locale, string $key): self
    {
        return new self(
            message: sprintf(
                'The translation key "%s" for locale "%s" is not valid: a key is a string that is not empty and not a '
                . 'decimal integer.',
                self::printable($key),
                $locale->code,
            ),
            context: ['locale' => $locale->code, 'key' => $key],
        );
    }

    public static function nonStringMessage(Locale $locale, string $key): self
    {
        return new self(
            message: sprintf(
                'The translation "%s" for locale "%s" is not a string.',
                self::printable($key),
                $locale->code,
            ),
            context: ['locale' => $locale->code, 'key' => $key],
        );
    }
}
