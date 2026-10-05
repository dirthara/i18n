<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Duration;

use ValueError;
use IntlException;
use MessageFormatter;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\ListType;
use Dirthara\I18n\Enum\ListWidth;
use Dirthara\I18n\Formatter\IcuData;
use Dirthara\I18n\Enum\DurationStyle;
use NumberFormatter as IntlNumberFormatter;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Formatter\List\ListFormatter;
use Dirthara\I18n\Exception\InvalidNumberException;
use Dirthara\I18n\Contract\DurationFormatter as DurationFormatterContract;

use function floor;
use function round;
use function intdiv;
use function strlen;
use function is_float;
use function is_finite;
use function preg_replace_callback;

final class DurationFormatter implements DurationFormatterContract
{
    private const string FORMATTER = 'duration';

    /**
     * @var array<string, int>
     */
    private const array UNITS = ['day' => 86_400, 'hour' => 3600, 'minute' => 60];

    /**
     * @var array<string, MessageFormatter>
     */
    private static array $units = [];

    /**
     * @var array<string, IntlNumberFormatter>
     */
    private static array $digits = [];

    /**
     * @var array<string, string>
     */
    private static array $patterns = [];

    public function __construct(
        public readonly Locale $locale,
    ) {}

    /**
     * @throws FormatterException
     * @throws InvalidNumberException
     */
    public function format(int|float $seconds, DurationStyle $style = DurationStyle::Long): string
    {
        if (is_float($seconds) && !is_finite($seconds)) {
            throw InvalidNumberException::notFinite(self::FORMATTER, $this->locale, $seconds);
        }

        if ($seconds < 0) {
            throw InvalidNumberException::negative(self::FORMATTER, $this->locale, $seconds);
        }

        return match ($style) {
            DurationStyle::Long => $this->spelled($seconds, 'unit-width-full-name', ListWidth::Wide),
            DurationStyle::Short => $this->spelled($seconds, 'unit-width-short', ListWidth::Short),
            DurationStyle::Narrow => $this->spelled($seconds, 'unit-width-narrow', ListWidth::Narrow),
            DurationStyle::Digital => $this->digital((int) round($seconds)),
        };
    }

    /**
     * @throws FormatterException
     */
    private function spelled(int|float $seconds, string $width, ListWidth $listWidth): string
    {
        $whole = (int) floor($seconds);
        $parts = [];

        foreach (self::UNITS as $unit => $length) {
            $amount = intdiv($whole, $length);
            $whole %= $length;

            if ($amount > 0) {
                $parts[] = $this->unit($unit, $amount, $width);
            }
        }

        $rest = $whole + ($seconds - floor($seconds));

        if ($rest > 0 || $parts === []) {
            $parts[] = $this->unit('second', $rest, $width);
        }

        return new ListFormatter($this->locale)->format($parts, ListType::Units, $listWidth);
    }

    /**
     * @throws FormatterException
     */
    private function digital(int $seconds): string
    {
        $hours = intdiv($seconds, num2: 3600);
        $minutes = intdiv($seconds % 3600, num2: 60);

        return (string) preg_replace_callback('/h+|m+|s+/', fn(array $field): string => $this->digits(match (
            $field[0][0]
        ) {
            'h' => $hours,
            'm' => $minutes,
            default => $seconds % 60,
        }, strlen($field[0])), $this->pattern($hours > 0 ? 'hms' : 'ms'));
    }

    /**
     * @throws FormatterException
     */
    private function unit(string $unit, int|float $amount, string $width): string
    {
        $style = $unit . '-' . $width;
        $formatter = self::$units[$this->locale->code . '|' . $style] ?? null;

        if ($formatter === null) {
            try {
                $formatter = new MessageFormatter(
                    $this->locale->code,
                    '{0, number, :: measure-unit/duration-' . $unit . ' ' . $width . '}',
                );
            } catch (IntlException $exception) {
                throw FormatterException::creationFailed(self::FORMATTER, $this->locale, $style, previous: $exception);
            }

            self::$units[$this->locale->code . '|' . $style] = $formatter;
        }

        $failure = null;

        try {
            $formatted = $formatter->format([$amount]);
        } catch (IntlException $failure) {
            $formatted = false;
        }

        if ($formatted === false) {
            throw FormatterException::formatFailed(
                self::FORMATTER,
                $this->locale,
                $style,
                $formatter->getErrorCode(),
                $formatter->getErrorMessage(),
                previous: $failure,
            );
        }

        return $formatted;
    }

    /**
     * @throws FormatterException
     */
    private function digits(int $value, int $width): string
    {
        $style = 'digital-' . $width;
        $formatter = self::$digits[$this->locale->code . '|' . $style] ?? null;

        if ($formatter === null) {
            try {
                $formatter = new IntlNumberFormatter($this->locale->code, IntlNumberFormatter::DECIMAL);

                // @mago-expect analysis:avoid-catching-error
            } catch (IntlException|ValueError $exception) {
                throw FormatterException::creationFailed(self::FORMATTER, $this->locale, $style, previous: $exception);
            }

            $formatter->setAttribute(IntlNumberFormatter::MIN_INTEGER_DIGITS, $width);
            $formatter->setAttribute(IntlNumberFormatter::GROUPING_USED, 0);
            self::$digits[$this->locale->code . '|' . $style] = $formatter;
        }

        $failure = null;

        try {
            $formatted = $formatter->format($value);
        } catch (IntlException $failure) {
            $formatted = false;
        }

        if ($formatted === false) {
            throw FormatterException::formatFailed(
                self::FORMATTER,
                $this->locale,
                $style,
                $formatter->getErrorCode(),
                $formatter->getErrorMessage(),
                previous: $failure,
            );
        }

        return $formatted;
    }

    private function pattern(string $fields): string
    {
        $key = $this->locale->code . '|' . $fields;
        $pattern = self::$patterns[$key] ?? null;

        if ($pattern !== null) {
            return $pattern;
        }

        $default = $fields === 'hms' ? 'h:mm:ss' : 'm:ss';
        $pattern = new IcuData()->string($this->locale, 'ICUDATA-unit', 'durationUnits', $fields) ?? $default;
        self::$patterns[$key] = $pattern;

        return $pattern;
    }
}
