<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Number;

use ValueError;
use IntlException;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\CompactNumberStyle;
use NumberFormatter as IntlNumberFormatter;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Exception\InvalidNumberException;
use Dirthara\I18n\Contract\NumberFormatter as NumberFormatterContract;

use function floor;
use function is_float;
use function is_finite;

final class NumberFormatter implements NumberFormatterContract
{
    private const string FORMATTER = 'number';

    /**
     * @var array<string, IntlNumberFormatter>
     */
    private static array $formatters = [];

    public function __construct(
        public readonly Locale $locale,
    ) {}

    /**
     * @throws FormatterException
     * @throws InvalidNumberException
     */
    public function format(int|float $number): string
    {
        return $this->formatWith('decimal', IntlNumberFormatter::DECIMAL, $number);
    }

    /**
     * @throws FormatterException
     * @throws InvalidNumberException
     */
    public function compact(int|float $number, CompactNumberStyle $style = CompactNumberStyle::Short): string
    {
        return match ($style) {
            CompactNumberStyle::Short => $this->formatWith(
                'compact-short',
                IntlNumberFormatter::DECIMAL_COMPACT_SHORT,
                $number,
            ),
            CompactNumberStyle::Long => $this->formatWith(
                'compact-long',
                IntlNumberFormatter::DECIMAL_COMPACT_LONG,
                $number,
            ),
        };
    }

    /**
     * @throws FormatterException
     * @throws InvalidNumberException
     */
    public function scientific(int|float $number): string
    {
        return $this->formatWith('scientific', IntlNumberFormatter::SCIENTIFIC, $number);
    }

    /**
     * @throws FormatterException
     * @throws InvalidNumberException
     */
    public function spellOut(int|float $number): string
    {
        return $this->formatWith('spell-out', IntlNumberFormatter::SPELLOUT, $number);
    }

    /**
     * @throws FormatterException
     * @throws InvalidNumberException
     */
    public function ordinal(int|float $number): string
    {
        if (is_float($number) && is_finite($number) && floor($number) !== $number) {
            throw InvalidNumberException::notWhole(self::FORMATTER, $this->locale, $number);
        }

        return $this->formatWith('ordinal', IntlNumberFormatter::ORDINAL, $number);
    }

    /**
     * @throws FormatterException
     * @throws InvalidNumberException
     */
    private function formatWith(string $style, int $intlStyle, int|float $number): string
    {
        if (is_float($number) && !is_finite($number)) {
            throw InvalidNumberException::notFinite(self::FORMATTER, $this->locale, $number);
        }

        $formatter = $this->formatter($style, $intlStyle);
        $formatted = $formatter->format($number);

        if ($formatted === false) {
            throw FormatterException::formatFailed(
                self::FORMATTER,
                $this->locale,
                $style,
                $formatter->getErrorCode(),
                $formatter->getErrorMessage(),
            );
        }

        return $formatted;
    }

    /**
     * @throws FormatterException
     */
    private function formatter(string $style, int $intlStyle): IntlNumberFormatter
    {
        $key = $this->locale->code . '|' . $style;
        $formatter = self::$formatters[$key] ?? null;

        if ($formatter !== null) {
            return $formatter;
        }

        try {
            $formatter = new IntlNumberFormatter($this->locale->code, $intlStyle);

            // @mago-expect analysis:avoid-catching-error
        } catch (IntlException|ValueError $exception) {
            throw FormatterException::creationFailed(self::FORMATTER, $this->locale, $style, previous: $exception);
        }

        $formatter->setAttribute(IntlNumberFormatter::ROUNDING_MODE, IntlNumberFormatter::ROUND_HALFUP);
        self::$formatters[$key] = $formatter;

        return $formatter;
    }
}
