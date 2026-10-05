<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use Dirthara\I18n\Locale;
use InvalidArgumentException;

use function is_nan;
use function sprintf;

final class InvalidNumberException extends InvalidArgumentException implements I18nException
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

    public static function notFinite(string $formatter, Locale $locale, float $number): self
    {
        $described = match (true) {
            is_nan($number) => 'NAN',
            $number > 0 => 'INF',
            default => '-INF',
        };

        return new self(
            message: sprintf(
                'The %s formatter for locale "%s" cannot format %s: it has to be a finite number.',
                $formatter,
                $locale->code,
                $described,
            ),
            context: ['formatter' => $formatter, 'locale' => $locale->code, 'number' => $described],
        );
    }

    public static function notWhole(string $formatter, Locale $locale, float $number): self
    {
        return new self(
            message: sprintf(
                'The %s formatter for locale "%s" cannot format %s as an ordinal: it has to be a whole number.',
                $formatter,
                $locale->code,
                (string) $number,
            ),
            context: ['formatter' => $formatter, 'locale' => $locale->code, 'number' => $number],
        );
    }
}
