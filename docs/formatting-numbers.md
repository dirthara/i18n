---
id: formatting-numbers
title: Formatting numbers
sidebar_position: 8
description: Formatting numbers for a locale, compact and scientific notation, spelled-out numbers, ordinals, and the factory.
---

`Dirthara\I18n\Formatter\Number\NumberFormatter` implements `Dirthara\I18n\Contract\NumberFormatter`. It writes a
number the way its locale does, through ICU and the `intl` extension.

```php
use Dirthara\I18n\Locale;
use Dirthara\I18n\Formatter\Number\NumberFormatter;

$formatter = new NumberFormatter(new Locale('en-GB'));

$formatter->format(1234567.891);   // '1,234,567.891'
$formatter->compact(1234567);      // '1.2M'
$formatter->scientific(0.000125);  // '1.25E-4'
$formatter->spellOut(42);          // 'forty-two'
$formatter->ordinal(22);           // '22nd'
```

| Method         | `en-GB`                               | `nl-NL`                  | Meaning                                         |
|----------------|---------------------------------------|--------------------------|-------------------------------------------------|
| `format()`     | `1,234,567.891`                       | `1.234.567,891`          | The number with the locale's separators, and up to three decimals. |
| `compact()`    | `1.2M`, or `1.2 million` with `CompactNumberStyle::Long` | `1,2 mln.`, `1,2 miljoen` | The number shortened to its magnitude. `Short` is the default. |
| `scientific()` | `1.234567891E6`                       | `1,234567891E6`          | The number in scientific notation.              |
| `spellOut()`   | `forty-two`                           | `tweeënveertig`          | The number in words.                            |
| `ordinal()`    | `22nd`                                | `22e`                    | The number as a position in a sequence.         |

A half is rounded away from zero, so `2.0005` is `2.001`. Locales write numbers with characters that are not always
plain spaces or hyphens: French groups thousands with a narrow no-break space (U+202F), and Dutch and German spelled-out
numbers contain soft hyphens (U+00AD), which only show where a line breaks.

An ordinal is a position, so `ordinal()` only takes a whole number: `3` and `3.0` are `3rd`, and `1.5` throws an
`InvalidNumberException`. No method formats `NAN`, `INF`, or `-INF`: they throw an `InvalidNumberException` too.

## Formatter factory

`Dirthara\I18n\Formatter\Number\NumberFormatterFactory` implements `Dirthara\I18n\Contract\Factory\NumberFormatterFactory`
and creates a number formatter for a locale:

```php
use Dirthara\I18n\Formatter\Number\NumberFormatterFactory;

$formatter = new NumberFormatterFactory()->create(new Locale('nl-NL'));
$formatter->format(1500);   // '1.500'
```

Creating a formatter is cheap: the ICU formatter behind each locale and method is created once, the first time it is
used, and shared by every `NumberFormatter` for that locale.
