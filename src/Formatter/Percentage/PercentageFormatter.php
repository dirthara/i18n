<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Percentage;

use ValueError;
use IntlException;
use NumberFormatter;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Exception\InvalidNumberException;
use Dirthara\I18n\Contract\PercentageFormatter as PercentageFormatterContract;

use function is_float;
use function is_finite;

final class PercentageFormatter implements PercentageFormatterContract
{
    private const string FORMATTER = 'percentage';

    private const string STYLE = 'percent';

    /**
     * @var array<string, NumberFormatter>
     */
    private static array $formatters = [];

    public function __construct(
        public readonly Locale $locale,
    ) {}

    /**
     * @throws FormatterException
     * @throws InvalidNumberException
     */
    public function format(int|float $fraction): string
    {
        if (is_float($fraction) && !is_finite($fraction)) {
            throw InvalidNumberException::notFinite(self::FORMATTER, $this->locale, $fraction);
        }

        $formatter = $this->formatter();
        $formatted = $formatter->format($fraction);

        if ($formatted === false) {
            throw FormatterException::formatFailed(
                self::FORMATTER,
                $this->locale,
                self::STYLE,
                $formatter->getErrorCode(),
                $formatter->getErrorMessage(),
            );
        }

        return $formatted;
    }

    /**
     * @throws FormatterException
     */
    private function formatter(): NumberFormatter
    {
        $formatter = self::$formatters[$this->locale->code] ?? null;

        if ($formatter !== null) {
            return $formatter;
        }

        try {
            $formatter = new NumberFormatter($this->locale->code, NumberFormatter::PERCENT);

            // @mago-expect analysis:avoid-catching-error
        } catch (IntlException|ValueError $exception) {
            throw FormatterException::creationFailed(self::FORMATTER, $this->locale, self::STYLE, previous: $exception);
        }

        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, NumberFormatter::ROUND_HALFUP);
        self::$formatters[$this->locale->code] = $formatter;

        return $formatter;
    }
}
