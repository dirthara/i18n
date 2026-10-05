---
id: formatting-durations
title: Formatting durations
sidebar_position: 12
description: Formatting a duration in seconds for a locale, the duration styles, and the duration formatter factory.
---

`Dirthara\I18n\Formatter\Duration\DurationFormatter` implements `Dirthara\I18n\Contract\DurationFormatter`. It writes a
length of time, given in seconds, the way its locale does.

```php
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\DurationStyle;
use Dirthara\I18n\Formatter\Duration\DurationFormatter;

$formatter = new DurationFormatter(new Locale('en-GB'));

$formatter->format(3725);                          // '1 hour, 2 minutes, 5 seconds'
$formatter->format(3725, DurationStyle::Short);    // '1 hr, 2 mins, 5 secs'
$formatter->format(3725, DurationStyle::Narrow);   // '1h 2m 5s'
$formatter->format(3725, DurationStyle::Digital);  // '1:02:05'
```

| Style     | `en-GB`, 3725 seconds          | `nl-NL`                          | Meaning                                  |
|-----------|--------------------------------|----------------------------------|------------------------------------------|
| `Long`    | `1 hour, 2 minutes, 5 seconds` | `1 uur, 2 minuten en 5 seconden` | Every unit written out. The default.     |
| `Short`   | `1 hr, 2 mins, 5 secs`         | `1 uur, 2 min, 5 sec`            | Abbreviated units.                       |
| `Narrow`  | `1h 2m 5s`                     | `1 u, 2 m, 5 s`                  | The shortest units the locale has.       |
| `Digital` | `1:02:05`                      | `1:02:05`                        | Hours, minutes, and seconds as on a clock. |

A duration is split into days, hours, minutes, and seconds, and a unit that is zero is left out: 3600 seconds is
`1 hour`, and 0 seconds is `0 seconds`. A fraction of a second stays on the seconds, as in `1 minute, 1.25 seconds`.
The units are joined as the locale joins the parts of a quantity, and every unit is written in the form its number
needs, which ICU takes from CLDR: in Arabic, two minutes is `دقيقتان`, the dual of minute.

The `Digital` style counts hours past a day, as in `25:01:01`, leaves the hours out of a duration under an hour, as in
`2:05`, and rounds to whole seconds, a half up. Its separator comes from the locale's CLDR data, read through `ResourceBundle` (see
[ICU data](installation.md#icu-data)): Danish and Finnish write `1.02.05`.

A duration cannot be negative: a negative number of seconds, like `NAN`, `INF`, and `-INF`, throws an
`InvalidNumberException`.

## Formatter factory

`Dirthara\I18n\Formatter\Duration\DurationFormatterFactory` implements
`Dirthara\I18n\Contract\Factory\DurationFormatterFactory` and creates a duration formatter for a locale:

```php
use Dirthara\I18n\Formatter\Duration\DurationFormatterFactory;

new DurationFormatterFactory()->create(new Locale('nl-NL'))->format(125);   // '2 minuten en 5 seconden'
```

The ICU formatters behind each locale, unit, and style are created once, the first time they are used, and shared by
every `DurationFormatter` for that locale.
