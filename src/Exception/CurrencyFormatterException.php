<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use IntlException;
use RuntimeException;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Currency;
use Dirthara\I18n\Enum\CurrencyStyle;

use function sprintf;

final class CurrencyFormatterException extends RuntimeException implements I18nException
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

    public static function creationFailed(Locale $locale, CurrencyStyle $style, IntlException $previous): self
    {
        return new self(
            message: sprintf(
                'Unable to create the %s currency formatter for locale "%s".',
                $style->name,
                $locale->code,
            ),
            previous: $previous,
            context: ['locale' => $locale->code, 'style' => $style->name],
        );
    }

    public static function formatFailed(
        Locale $locale,
        Currency $currency,
        CurrencyStyle $style,
        int $intlCode,
        string $intlMessage,
    ): self {
        return new self(
            message: sprintf(
                'Unable to format an amount of %s in the %s style for locale "%s": %s.',
                $currency->code,
                $style->name,
                $locale->code,
                self::printable($intlMessage),
            ),
            context: [
                'locale' => $locale->code,
                'currency' => $currency->code,
                'style' => $style->name,
                'intlCode' => $intlCode,
                'intlMessage' => $intlMessage,
            ],
        );
    }
}
