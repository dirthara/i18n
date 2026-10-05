<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use RuntimeException;
use Dirthara\I18n\Locale;

use function sprintf;

final class PluralRulesException extends RuntimeException implements I18nException
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

    public static function formatFailed(Locale $locale, int|float $count, int $intlCode, string $intlMessage): self
    {
        return new self(
            message: sprintf(
                'Unable to determine the plural category of %s for locale "%s": %s.',
                (string) $count,
                $locale->code,
                self::printable($intlMessage),
            ),
            context: [
                'locale' => $locale->code,
                'count' => $count,
                'intlCode' => $intlCode,
                'intlMessage' => $intlMessage,
            ],
        );
    }

    public static function unknownCategory(Locale $locale, int|float $count, string $category): self
    {
        return new self(
            message: sprintf(
                'The plural rules of locale "%s" gave the unknown category "%s" for %s.',
                $locale->code,
                self::printable($category),
                (string) $count,
            ),
            context: ['locale' => $locale->code, 'count' => $count, 'category' => $category],
        );
    }
}
