<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\DateTime;

use DateTimeZone;
use IntlException;
use DateTimeInterface;
use IntlDateFormatter;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\DateStyle;
use Dirthara\I18n\Enum\TimeStyle;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Exception\InvalidDateTimeStyleException;
use Dirthara\I18n\Contract\DateTimeFormatter as DateTimeFormatterContract;

use function strtolower;

final class DateTimeFormatter implements DateTimeFormatterContract
{
    private const string FORMATTER = 'date-time';

    /**
     * @var array<string, IntlDateFormatter>
     */
    private static array $formatters = [];

    public function __construct(
        public readonly Locale $locale,
        public readonly DateTimeZone $timezone,
    ) {}

    /**
     * @throws FormatterException
     * @throws InvalidDateTimeStyleException
     */
    public function format(
        DateTimeInterface $dateTime,
        DateStyle $dateStyle = DateStyle::Medium,
        TimeStyle $timeStyle = TimeStyle::None,
    ): string {
        if ($dateStyle === DateStyle::None && $timeStyle === TimeStyle::None) {
            throw InvalidDateTimeStyleException::nothingToFormat($this->locale);
        }

        $style = strtolower($dateStyle->name . '-' . $timeStyle->name);
        $formatter = $this->formatter($style, $dateStyle, $timeStyle);
        $formatted = $formatter->format($dateTime);

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
     * @throws InvalidDateTimeStyleException
     */
    public function formatDate(DateTimeInterface $dateTime, DateStyle $dateStyle = DateStyle::Medium): string
    {
        return $this->format($dateTime, $dateStyle, TimeStyle::None);
    }

    /**
     * @throws FormatterException
     * @throws InvalidDateTimeStyleException
     */
    public function formatTime(DateTimeInterface $dateTime, TimeStyle $timeStyle = TimeStyle::Medium): string
    {
        return $this->format($dateTime, DateStyle::None, $timeStyle);
    }

    /**
     * @throws FormatterException
     */
    private function formatter(string $style, DateStyle $dateStyle, TimeStyle $timeStyle): IntlDateFormatter
    {
        $key = $this->locale->code . '|' . $this->timezone->getName() . '|' . $style;
        $formatter = self::$formatters[$key] ?? null;

        if ($formatter !== null) {
            return $formatter;
        }

        try {
            $formatter = new IntlDateFormatter(
                $this->locale->code,
                match ($dateStyle) {
                    DateStyle::None => IntlDateFormatter::NONE,
                    DateStyle::Short => IntlDateFormatter::SHORT,
                    DateStyle::Medium => IntlDateFormatter::MEDIUM,
                    DateStyle::Long => IntlDateFormatter::LONG,
                    DateStyle::Full => IntlDateFormatter::FULL,
                    DateStyle::RelativeShort => IntlDateFormatter::RELATIVE_SHORT,
                    DateStyle::RelativeMedium => IntlDateFormatter::RELATIVE_MEDIUM,
                    DateStyle::RelativeLong => IntlDateFormatter::RELATIVE_LONG,
                    DateStyle::RelativeFull => IntlDateFormatter::RELATIVE_FULL,
                },
                match ($timeStyle) {
                    TimeStyle::None => IntlDateFormatter::NONE,
                    TimeStyle::Short => IntlDateFormatter::SHORT,
                    TimeStyle::Medium => IntlDateFormatter::MEDIUM,
                    TimeStyle::Long => IntlDateFormatter::LONG,
                    TimeStyle::Full => IntlDateFormatter::FULL,
                },
                $this->timezone,
            );
        } catch (IntlException $exception) {
            throw FormatterException::creationFailed(self::FORMATTER, $this->locale, $style, previous: $exception);
        }

        self::$formatters[$key] = $formatter;

        return $formatter;
    }
}
