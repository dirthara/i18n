<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\RelativeDateTime;

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

use function strtr;
use function intdiv;
use function implode;
use function array_unique;

final class RelativeDateTimeFormatter implements RelativeDateTimeFormatterContract
{
    /**
     * @var array<string, string>
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
        $difference = DateTimeImmutable::createFromInterface($relativeTo)
            ->setTimezone($this->timezone)
            ->diff(DateTimeImmutable::createFromInterface($dateTime)->setTimezone($this->timezone));
        $suffix = match ($style) {
            RelativeDateTimeStyle::Long => '',
            RelativeDateTimeStyle::Short => '-short',
            RelativeDateTimeStyle::Narrow => '-narrow',
        };

        [$field, $amount] = match (true) {
            $difference->y > 0 => ['year', $difference->y],
            $difference->m > 0 => ['month', $difference->m],
            $difference->d >= 7 => ['week', intdiv($difference->d, num2: 7)],
            $difference->d > 0 => ['day', $difference->d],
            $difference->h > 0 => ['hour', $difference->h],
            $difference->i > 0 => ['minute', $difference->i],
            $difference->s > 0 => ['second', $difference->s],
            default => [null, 0],
        };

        if ($field === null) {
            return $this->pattern('second', $suffix, ['relative', '0']);
        }

        $direction = $difference->invert === 1 ? 'past' : 'future';
        $category = new PluralRules($this->locale)->category($amount)->value;

        return strtr(
            $this->pattern(
                $field,
                $suffix,
                ['relativeTime', $direction, $category],
                ['relativeTime', $direction, 'other'],
            ),
            ['{0}' => new NumberFormatter($this->locale)->format($amount)],
        );
    }

    /**
     * Each style of a field is tried in the locale's own bundle before a less specific bundle, because ICU's root
     * bundle only aliases the shorter styles to the longer ones of the same locale.
     *
     * @param list<string> ...$paths
     */
    private function pattern(string $field, string $suffix, array ...$paths): string
    {
        $key = $this->locale->code . '|' . $field . $suffix . '|' . implode('/', $paths[0]);
        $pattern = self::$patterns[$key] ?? null;

        if ($pattern !== null) {
            return $pattern;
        }

        $data = new IcuData();
        $tables = array_unique([$field . $suffix, $field . ($suffix === '' ? '' : '-short'), $field]);

        foreach ($data->candidates($this->locale) as $candidate) {
            foreach ($tables as $table) {
                foreach ($paths as $path) {
                    $pattern = $data->stringFrom($candidate, 'ICUDATA', 'fields', $table, ...$path);

                    if ($pattern !== null) {
                        return self::$patterns[$key] = $pattern;
                    }
                }
            }
        }

        return self::$patterns[$key] = $data->stringFrom('root', 'ICUDATA', 'fields', $field, ...$paths[0]) ?? '{0}';
    }
}
