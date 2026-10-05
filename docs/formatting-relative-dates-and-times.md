---
id: formatting-relative-dates-and-times
title: Formatting relative dates and times
sidebar_position: 13
description: Writing how far a moment is from another, such as yesterday, next week, or in 6 weeks, for a locale.
---

`Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatter` implements
`Dirthara\I18n\Contract\RelativeDateTimeFormatter`. It writes how far one moment is from another the way its locale
does: with a name where the locale has one, such as `yesterday` or `next week`, and with a number otherwise, such as
`3 days ago` or `in 6 weeks`.

```php
use DateTimeZone;
use DateTimeImmutable;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\RelativeDateTimeStyle;
use Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatter;

$formatter = new RelativeDateTimeFormatter(new Locale('en-GB'), new DateTimeZone('Europe/Amsterdam'));
$now = new DateTimeImmutable();

$formatter->format($now, $now);                                                   // 'now'
$formatter->format($now->modify('-1 day'), $now);                                 // 'yesterday'
$formatter->format($now->modify('+1 week'), $now);                                // 'next week'
$formatter->format($now->modify('+6 weeks'), $now);                               // 'in 6 weeks'
$formatter->format($now->modify('-2 hours'), $now, RelativeDateTimeStyle::Narrow); // '2h ago'
```

| Argument     | Type                                       | Default                       | Meaning                       |
|--------------|--------------------------------------------|-------------------------------|-------------------------------|
| `dateTime`   | `DateTimeInterface`                        |                               | The moment to describe.       |
| `relativeTo` | `DateTimeInterface`                        |                               | The moment it is relative to, usually now. |
| `style`      | `Dirthara\I18n\Enum\RelativeDateTimeStyle` | `RelativeDateTimeStyle::Long` | `Long`, `Short`, or `Narrow` units. |

## Choosing the unit

The difference between the two moments is taken in the formatter's timezone, whatever the timezones of the moments
themselves, as calendar units, so a day across a change to or from summer time is still one day. The unit is the first
of these that applies:

1. **Whole years:** the difference is an exact number of years, with nothing left over, such as from 5 October 2026 to
   5 October 2027.
2. **Whole months:** the difference is an exact number of calendar months, counted in total: 14 months exactly is
   `in 14 months`.
3. **Years:** any other difference of a year or more, in whole years, rounded down: 400 days is `next year`.
4. **Weeks:** a difference of at least seven days, in whole weeks, rounded down: 6 weeks is `in 6 weeks`, and 40 days
   is `in 5 weeks`.
5. **Days**, then **hours**, **minutes**, and **seconds**, each rounded down. A difference of less than a second is the
   same moment.

Only the two moments decide the unit, not how they were made: 31 January to 28 February is 28 days, so `in 4 weeks`,
not a whole month.

## Named and numeric forms

When the locale has a name for the amount of the unit, the name is used; otherwise the number is written with the
locale's numeric pattern, in the plural form the number needs, with the locale's digits.

| Difference | `en-GB`      | `nl-NL`         | `de-DE`         | `fr-FR`                 | `ja-JP` |
|------------|--------------|-----------------|-----------------|-------------------------|---------|
| none       | `now`        | `nu`            | `jetzt`         | `maintenant`            | `今`    |
| −2 days    | `2 days ago` | `eergisteren`   | `vorgestern`    | `avant-hier`            | `一昨日` |
| −1 day     | `yesterday`  | `gisteren`      | `gestern`       | `hier`                  | `昨日`  |
| +1 day     | `tomorrow`   | `morgen`        | `morgen`        | `demain`                | `明日`  |
| +3 days    | `in 3 days`  | `over 3 dagen`  | `in 3 Tagen`    | `dans 3 jours`          | `3 日後` |
| −1 week    | `last week`  | `vorige week`   | `letzte Woche`  | `la semaine dernière`   | `先週`  |
| +6 weeks   | `in 6 weeks` | `over 6 weken`  | `in 6 Wochen`   | `dans 6 semaines`       | `6 週間後` |
| +1 month   | `next month` | `volgende maand`| `nächsten Monat`| `le mois prochain`      | `来月`  |
| −1 year    | `last year`  | `vorig jaar`    | `letztes Jahr`  | `l’année dernière`      | `昨年`  |

Which amounts have names depends on the locale: English names one day, week, month, or year either way, and Dutch,
German, French, Arabic, and Japanese also name two days. Hours, minutes, and seconds are always numbers. Arabic shows how
the plural forms are kept: two hours ago is `قبل ساعتين`, with the dual.

A locale that has no shorter style of a unit uses its longer one, as ICU does: Dutch has no narrow weeks, so
`RelativeDateTimeStyle::Narrow` writes `over 6 weken`. A language ICU knows but has no locale data for, such as Klingon
(`tlh`), gets CLDR's neutral numeric forms, such as `+1 d` and `+0 s`, rather than CLDR's English names. A language ICU
does not know at all, such as `zz`, throws a `FormatterException`, as the other formatters do.

:::note
PHP's `intl` extension has no relative date-time formatter, so this one reads CLDR's relative time data through
`ResourceBundle`. See [ICU data](installation.md#icu-data) for what that means across ICU versions.
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
