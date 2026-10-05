---
id: formatting-locales
title: Formatting locales
sidebar_position: 10
description: Naming locales and their language, region, script, and variants in another locale, and the factory.
---

`Dirthara\I18n\Formatter\Locale\LocaleFormatter` implements `Dirthara\I18n\Contract\LocaleFormatter`. It names a
[locale](intro.md), and each part of it, in its own locale: a language picker can show `Nederlands` for `nl-NL` to a
Dutch user and `Dutch (Netherlands)` to a British one.

```php
use Dirthara\I18n\Locale;
use Dirthara\I18n\Formatter\Locale\LocaleFormatter;

$formatter = new LocaleFormatter(new Locale('en-GB'));
$locale = new Locale('zh-Hant-TW');

$formatter->format($locale);     // 'Chinese (Traditional, Taiwan)'
$formatter->language($locale);   // 'Chinese'
$formatter->region($locale);     // 'Taiwan'
$formatter->script($locale);     // 'Traditional Han'
$formatter->variants($locale);   // []
```

| Method       | Returns          | Meaning                                                                        |
|--------------|------------------|--------------------------------------------------------------------------------|
| `format()`   | `string`         | The name of the whole locale: its language, with its script, region, and variants in parentheses. |
| `language()` | `string`         | The name of the locale's language.                                             |
| `region()`   | `?string`        | The name of the locale's region, or `null` when the locale has none.           |
| `script()`   | `?string`        | The name of the locale's script, or `null` when the locale has none.           |
| `variants()` | `list<string>`   | The name of each of the locale's variants, in their order.                     |

The names come from CLDR, through ICU and the `intl` extension. A script is named differently inside a locale's name
than on its own, as CLDR names it: `Traditional` in `Chinese (Traditional, Taiwan)`, but `Traditional Han` from
`script()`. Every variant is named, and they are joined with the separator of the formatter's locale:

```php
$formatter->format(new Locale('de-CH-1901-1996'));
// 'German (Switzerland, Traditional German orthography, German orthography of 1996)'
```

A part CLDR has no name for, such as the unassigned language `zz` or region `AA`, is shown by its code.

## Formatter factory

`Dirthara\I18n\Formatter\Locale\LocaleFormatterFactory` implements
`Dirthara\I18n\Contract\Factory\LocaleFormatterFactory` and creates a locale formatter for a locale:

```php
use Dirthara\I18n\Formatter\Locale\LocaleFormatterFactory;

new LocaleFormatterFactory()->create(new Locale('nl-NL'))->format(new Locale('en'));   // 'Engels'
```
