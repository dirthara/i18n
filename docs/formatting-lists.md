---
id: formatting-lists
title: Formatting lists
sidebar_position: 9
description: Joining a list of strings for a locale, the list types and widths, and the list formatter factory.
---

`Dirthara\I18n\Formatter\List\ListFormatter` implements `Dirthara\I18n\Contract\ListFormatter`. It joins strings into a
list the way its locale does, with the separators and the conjunction of the locale, through the `IntlListFormatter`
class of the `intl` extension.

```php
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\ListType;
use Dirthara\I18n\Formatter\List\ListFormatter;

$formatter = new ListFormatter(new Locale('nl-NL'));

$formatter->format(['appels', 'peren', 'pruimen']);                 // 'appels, peren en pruimen'
$formatter->format(['appels', 'peren'], ListType::Or);              // 'appels of peren'
```

| Argument | Type                           | Default           | Meaning                                    |
|----------|--------------------------------|-------------------|--------------------------------------------|
| `items`  | `array<array-key, string>`     |                   | The strings to join, in the order of the array; the keys are ignored. |
| `type`   | `Dirthara\I18n\Enum\ListType`  | `ListType::And`   | `And` lists all items, `Or` offers a choice, and `Units` lists the parts of one quantity, such as `3 hours, 20 minutes`. |
| `width`  | `Dirthara\I18n\Enum\ListWidth` | `ListWidth::Wide` | How much space the locale's separators take: `Wide`, `Short`, or `Narrow`. |

| Type and width    | `en-GB`      | `nl-NL`     | `de-DE`       |
|-------------------|--------------|-------------|---------------|
| `And`, `Wide`     | `a, b and c` | `a, b en c` | `a, b und c`  |
| `And`, `Short`    | `a, b and c` | `a, b & c`  | `a, b und c`  |
| `And`, `Narrow`   | `a, b, c`    | `a, b, c`   | `a, b und c`  |
| `Or`, `Wide`      | `a, b or c`  | `a, b of c` | `a, b oder c` |
| `Units`, `Wide`   | `a, b, c`    | `a, b en c` | `a, b und c`  |
| `Units`, `Narrow` | `a b c`      | `a, b, c`   | `a, b und c`  |

An empty list is the empty string, and a list of one item is that item. The items are joined in the order the array
holds them, whatever their keys: `[2 => 'b', 0 => 'a', 'x' => 'c']` is `b, a and c`, so an array does not have to be a
list. Every item has to be a string: anything else throws an `InvalidListItemException` rather than
being converted, and a string that is not valid UTF-8 throws a `FormatterException`.

## Formatter factory

`Dirthara\I18n\Formatter\List\ListFormatterFactory` implements `Dirthara\I18n\Contract\Factory\ListFormatterFactory`
and creates a list formatter for a locale:

```php
use Dirthara\I18n\Formatter\List\ListFormatterFactory;

new ListFormatterFactory()->create(new Locale('de-DE'))->format(['a', 'b', 'c']);   // 'a, b und c'
```

The `IntlListFormatter` behind each locale, type, and width is created once, the first time it is used, and shared by
every `ListFormatter` for that locale.
