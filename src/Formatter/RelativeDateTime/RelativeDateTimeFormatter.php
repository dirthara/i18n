<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\RelativeDateTime;

use Closure;
use DateInterval;
use DateTimeZone;
use DateTimeImmutable;
use DateTimeInterface;
use Dirthara\I18n\Locale;
use Dirthara\I18n\PluralRules;
use Dirthara\I18n\Formatter\IcuData;
use Dirthara\I18n\Exception\I18nException;
use Dirthara\I18n\Enum\RelativeDateTimeStyle;
use Dirthara\I18n\Formatter\Number\NumberFormatter;
use Dirthara\I18n\Contract\RelativeDateTimeFormatter as RelativeDateTimeFormatterContract;

use function abs;
use function strtr;
use function intdiv;
use function array_unique;
use function array_key_exists;

final class RelativeDateTimeFormatter implements RelativeDateTimeFormatterContract
{
    /**
     * @var array<string, ?string>
     */
    private static array $patterns = [];

    public function __construct(
        public readonly Locale $locale,
        public readonly DateTimeZone $timezone,
    ) {}

    /**
     * @throws I18nException
     */
    public function format(
        DateTimeInterface $dateTime,
        DateTimeInterface $relativeTo,
        RelativeDateTimeStyle $style = RelativeDateTimeStyle::Long,
    ): string {
        $suffix = match ($style) {
            RelativeDateTimeStyle::Long => '',
            RelativeDateTimeStyle::Short => '-short',
            RelativeDateTimeStyle::Narrow => '-narrow',
        };

        [$field, $amount] = $this->unit(
            DateTimeImmutable::createFromInterface($relativeTo)
                ->setTimezone($this->timezone)
                ->diff(DateTimeImmutable::createFromInterface($dateTime)->setTimezone($this->timezone)),
        );

        $named = $this->named($field, $suffix, $amount);

        if ($named !== null) {
            return $named;
        }

        $count = abs($amount);
        $pattern = $this->numeric(
            $field,
            $suffix,
            $amount < 0 ? 'past' : 'future',
            new PluralRules($this->locale)->category($count)->value,
        );

        return strtr($pattern, ['{0}' => new NumberFormatter($this->locale)->format($count)]);
    }

    /**
     * @return array{string, int}
     */
    private function unit(DateInterval $difference): array
    {
        $sign = $difference->invert === 1 ? -1 : 1;
        $days = (int) $difference->days;
        $wholeDays = $difference->h === 0 && $difference->i === 0 && $difference->s === 0 && $difference->f === 0.0;

        return match (true) {
            $wholeDays && $difference->d === 0 && $difference->m === 0 && $difference->y > 0 => [
                'year',
                $sign * $difference->y,
            ],
            $wholeDays && $difference->d === 0 && ($difference->y > 0 || $difference->m > 0) => [
                'month',
                $sign * (($difference->y * 12) + $difference->m),
            ],
            $difference->y > 0 => ['year', $sign * $difference->y],
            $days >= 7 => ['week', $sign * intdiv($days, num2: 7)],
            $days > 0 => ['day', $sign * $days],
            $difference->h > 0 => ['hour', $sign * $difference->h],
            $difference->i > 0 => ['minute', $sign * $difference->i],
            default => ['second', $sign * $difference->s],
        };
    }

    private function named(string $field, string $suffix, int $amount): ?string
    {
        return $this->cached($field . $suffix . '|relative/' . $amount, fn(): ?string => $this->lookup(
            $field,
            $suffix,
            ['relative', (string) $amount],
        ));
    }

    /**
     * The root bundle's named forms are English, so only its neutral numeric forms serve as a fallback.
     */
    private function numeric(string $field, string $suffix, string $direction, string $category): string
    {
        $data = new IcuData();

        return (string) $this->cached(
            $field . $suffix . '|relativeTime/' . $direction . '/' . $category,
            fn(): ?string => (
                $this->lookup(
                    $field,
                    $suffix,
                    ['relativeTime', $direction, $category],
                    ['relativeTime', $direction, 'other'],
                ) ?? $data->stringFrom(
                    'root',
                    'ICUDATA',
                    'fields',
                    $field,
                    'relativeTime',
                    $direction,
                    $category,
                ) ?? $data->stringFrom('root', 'ICUDATA', 'fields', $field, 'relativeTime', $direction, 'other')
            ),
        );
    }

    /**
     * @param Closure(): ?string $lookup
     */
    private function cached(string $pattern, Closure $lookup): ?string
    {
        $key = $this->locale->code . '|' . $pattern;

        if (!array_key_exists($key, self::$patterns)) {
            self::$patterns[$key] = $lookup();
        }

        return self::$patterns[$key];
    }

    /**
     * ICU's root bundle aliases a short style to the long style of the same locale, so styles are tried per locale.
     *
     * @param list<string> ...$paths
     */
    private function lookup(string $field, string $suffix, array ...$paths): ?string
    {
        $data = new IcuData();
        $tables = array_unique([$field . $suffix, $field . ($suffix === '' ? '' : '-short'), $field]);

        foreach ($data->candidates($this->locale) as $candidate) {
            foreach ($tables as $table) {
                foreach ($paths as $path) {
                    $pattern = $data->stringFrom($candidate, 'ICUDATA', 'fields', $table, ...$path);

                    if ($pattern !== null) {
                        return $pattern;
                    }
                }
            }
        }

        return null;
    }
}
