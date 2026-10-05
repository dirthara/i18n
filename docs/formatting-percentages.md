---
id: formatting-percentages
title: Formatting percentages
sidebar_position: 9
description: Formatting fractions as percentages for a locale, and the percentage formatter factory.
---

`Dirthara\I18n\Formatter\Percentage\PercentageFormatter` implements `Dirthara\I18n\Contract\PercentageFormatter`. It
writes a fraction as a percentage the way its locale does: `0.25` is a quarter, `25%`.

```php
use Dirthara\I18n\Locale;
use Dirthara\I18n\Formatter\Percentage\PercentageFormatter;

new PercentageFormatter(new Locale('en-GB'))->format(0.25);   // '25%'
new PercentageFormatter(new Locale('de-DE'))->format(0.25);   // '25 %'
new PercentageFormatter(new Locale('tr-TR'))->format(0.25);   // '%25'
```

The locale decides where the percent sign goes and whether a space comes before it; German and French put a no-break
space (U+00A0) before it. A percentage is written without decimals, as CLDR does for every locale, and a half is
rounded away from zero: `0.125` is `13%`. `NAN`, `INF`, and `-INF` throw an `InvalidNumberException`.

## Formatter factory

`Dirthara\I18n\Formatter\Percentage\PercentageFormatterFactory` implements
`Dirthara\I18n\Contract\Factory\PercentageFormatterFactory` and creates a percentage formatter for a locale:

```php
use Dirthara\I18n\Formatter\Percentage\PercentageFormatterFactory;

new PercentageFormatterFactory()->create(new Locale('nl-NL'))->format(0.5);   // '50%'
```

The ICU formatter behind each locale is created once, the first time it is used, and shared by every
`PercentageFormatter` for that locale.
