---
id: formatting-relative-dates-and-times
title: Formatting relative dates and times
sidebar_position: 13
description: Writing how far a moment is from another, such as 3 days ago or in 2 hours, for a locale, and the factory.
---

`Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatter` implements
`Dirthara\I18n\Contract\RelativeDateTimeFormatter`. It writes how far one moment is from another the way its locale
does, such as `3 days ago` or `in 2 hours`.

```php
use DateTimeZone;
use DateTimeImmutable;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\RelativeDateTimeStyle;
use Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatter;

$formatter = new RelativeDateTimeFormatter(new Locale('en-GB'), new DateTimeZone('Europe/Amsterdam'));
$now = new DateTimeImmutable();

$formatter->format($now->modify('-3 days'), $now);                                // '3 days ago'
$formatter->format($now->modify('+2 hours'), $now, RelativeDateTimeStyle::Short); // 'in 2 hr.'
$formatter->format($now->modify('+2 hours'), $now, RelativeDateTimeStyle::Narrow);// 'in 2h'
$formatter->format($now, $now);                                                    // 'now'
```

| Argument     | Type                                       | Default                       | Meaning                       |
|--------------|--------------------------------------------|-------------------------------|-------------------------------|
| `dateTime`   | `DateTimeInterface`                        |                               | The moment to describe.       |
| `relativeTo` | `DateTimeInterface`                        |                               | The moment it is relative to, usually now. |
| `style`      | `Dirthara\I18n\Enum\RelativeDateTimeStyle` | `RelativeDateTimeStyle::Long` | `Long`, `Short`, or `Narrow` units. |

The difference is written in the largest whole unit it has: years, months, weeks, days, hours, minutes, or seconds,
rounded down. Fourteen months is `1 year ago` and eight days is `in 1 week`; a difference of nothing is `now`. Years,
months, and days are calendar units, counted in the formatter's timezone, whatever the timezones of the two moments, so
a day across a change to or from summer time is still one day.

The text comes from CLDR's relative time data, in the plural form the number needs, and the number is written with the
locale's digits:

| Locale  | `Long`, two hours ago | `Narrow`        |
|---------|-----------------------|-----------------|
| `en-GB` | `2 hours ago`         | `2h ago`        |
| `nl-NL` | `2 uur geleden`       | `2 uur geleden` |
| `de-DE` | `vor 2 Stunden`       | `vor 2 Std.`    |
| `ar`    | `قبل ساعتين`          | `قبل ساعتين`    |
| `ja-JP` | `2 時間前`            | `2時間前`       |

A locale that has no shorter form of a unit uses its longer form, as ICU does. A language ICU knows but has no locale
data for, such as Klingon (`tlh`), gets CLDR's root forms, such as `+3 d`. A language ICU does not know at all, such as
`zz`, throws a `FormatterException`, as the other formatters do.

:::note
PHP's `intl` extension has no relative date-time formatter, so this one reads CLDR's data through `ResourceBundle` and
fills it in itself. It always writes a number: `1 day ago` rather than `yesterday`. For the days around today written as
words, use the relative date styles of the [date-time formatter](formatting-dates-and-times.md).
:::

## Formatter factory

`Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatterFactory` implements
`Dirthara\I18n\Contract\Factory\RelativeDateTimeFormatterFactory` and creates a relative date-time formatter for a
locale and a timezone:

```php
use Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatterFactory;

$formatter = new RelativeDateTimeFormatterFactory()->create(new Locale('de-DE'), new DateTimeZone('Europe/Berlin'));
$formatter->format($now->modify('-3 days'), $now);   // 'vor 3 Tagen'
```

Each pattern is read from CLDR's data once, the first time it is used, and shared by every `RelativeDateTimeFormatter`
for that locale.
