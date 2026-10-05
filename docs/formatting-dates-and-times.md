---
id: formatting-dates-and-times
title: Formatting dates and times
sidebar_position: 12
description: Formatting dates and times for a locale in a timezone, the date and time styles, and the factory.
---

`Dirthara\I18n\Formatter\DateTime\DateTimeFormatter` implements `Dirthara\I18n\Contract\DateTimeFormatter`. It writes a
date, a time, or both the way its locale does, in its timezone, through `IntlDateFormatter` of the `intl` extension.

```php
use DateTimeZone;
use DateTimeImmutable;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\DateStyle;
use Dirthara\I18n\Enum\TimeStyle;
use Dirthara\I18n\Formatter\DateTime\DateTimeFormatter;

$formatter = new DateTimeFormatter(new Locale('en-GB'), new DateTimeZone('Europe/Amsterdam'));
$moment = new DateTimeImmutable('2026-10-05 14:30:15', new DateTimeZone('UTC'));

$formatter->format($moment);                                     // '5 Oct 2026'
$formatter->format($moment, DateStyle::Short, TimeStyle::Short); // '05/10/2026, 16:30'
$formatter->formatDate($moment, DateStyle::Full);                // 'Monday, 5 October 2026'
$formatter->formatTime($moment);                                 // '16:30:15'
```

| Method         | Default styles                          | Meaning                                       |
|----------------|-----------------------------------------|-----------------------------------------------|
| `format()`     | `DateStyle::Medium`, `TimeStyle::None`  | The date, the time, or both, as the styles ask. |
| `formatDate()` | `DateStyle::Medium`                     | The date only.                                |
| `formatTime()` | `TimeStyle::Medium`                     | The time only.                                |

A value is always written in the formatter's timezone, whatever the timezone of the `DateTimeInterface` itself: the
formatter above writes 14:30 UTC as `16:30`. Give each user a formatter for their own timezone. An offset such as
`+02:00` works as a timezone; an abbreviation such as `CEST` does not, because ICU cannot tell which zone it means, and
throws a `FormatterException` when the formatter is first used.

## Styles

| Style      | Date, `en-GB`            | Date, `en-US`             | Time, `en-GB`                          | Time, `en-US`                          |
|------------|--------------------------|---------------------------|----------------------------------------|----------------------------------------|
| `Short`    | `05/10/2026`             | `10/5/26`                 | `16:30`                                | `4:30 PM`                              |
| `Medium`   | `5 Oct 2026`             | `Oct 5, 2026`             | `16:30:15`                             | `4:30:15 PM`                           |
| `Long`     | `5 October 2026`         | `October 5, 2026`         | `16:30:15 CEST`                        | `4:30:15 PM GMT+2`                     |
| `Full`     | `Monday, 5 October 2026` | `Monday, October 5, 2026` | `16:30:15 Central European Summer Time` | `4:30:15 PM Central European Summer Time` |
| `None`     | no date                  | no date                   | no time                                | no time                                |

The relative date styles, `RelativeShort`, `RelativeMedium`, `RelativeLong`, and `RelativeFull`, write the days next to
today as words, such as `yesterday`, `today`, and `tomorrow`, and every other day as its non-relative style does. Which
day is today follows the formatter's timezone and the clock of the server.

A date and a time are joined as the locale joins them: `05/10/2026, 16:30` in British English, and
`Monday, 5 October 2026 at 16:30:15 Central European Summer Time` in the full style. Some locales use characters that
are not plain spaces: American English puts a narrow no-break space (U+202F) before `AM` and `PM`.

`DateStyle::None` with `TimeStyle::None` leaves nothing to format and throws an `InvalidDateTimeStyleException`, and so do
`formatDate()` with `DateStyle::None` and `formatTime()` with `TimeStyle::None`.

:::note
The formatter uses the Gregorian calendar for every locale, which is what `IntlDateFormatter` does unless it is told
otherwise. A locale whose CLDR default is another calendar, such as Thai with the Buddhist calendar, still gets
Gregorian dates.
:::

## Formatter factory

`Dirthara\I18n\Formatter\DateTime\DateTimeFormatterFactory` implements
`Dirthara\I18n\Contract\Factory\DateTimeFormatterFactory`. It creates a date-time formatter for a locale and a timezone,
which both usually depend on the user:

```php
use Dirthara\I18n\Formatter\DateTime\DateTimeFormatterFactory;

$formatter = new DateTimeFormatterFactory()->create(new Locale('nl-NL'), new DateTimeZone('Europe/Amsterdam'));
$formatter->formatDate($moment);   // '5 okt 2026'
```

The `IntlDateFormatter` behind each locale, timezone, and combination of styles is created once, the first time it is
used, and shared by every `DateTimeFormatter` with that locale and timezone.
