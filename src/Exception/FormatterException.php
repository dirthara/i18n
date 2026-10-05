<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use IntlException;
use RuntimeException;
use Dirthara\I18n\Locale;

use function sprintf;

final class FormatterException extends RuntimeException implements I18nException
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

    public static function creationFailed(
        string $formatter,
        Locale $locale,
        string $style,
        IntlException $previous,
    ): self {
        return new self(
            message: sprintf(
                'Unable to create the %s %s formatter for locale "%s".',
                $style,
                $formatter,
                $locale->code,
            ),
            previous: $previous,
            context: ['formatter' => $formatter, 'locale' => $locale->code, 'style' => $style],
        );
    }

    public static function formatFailed(
        string $formatter,
        Locale $locale,
        string $style,
        int $intlCode,
        string $intlMessage,
    ): self {
        return new self(
            message: sprintf(
                'The %s %s formatter for locale "%s" failed to format a value: %s.',
                $style,
                $formatter,
                $locale->code,
                self::printable($intlMessage),
            ),
            context: [
                'formatter' => $formatter,
                'locale' => $locale->code,
                'style' => $style,
                'intlCode' => $intlCode,
                'intlMessage' => $intlMessage,
            ],
        );
    }
}
