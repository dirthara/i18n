---
id: exceptions
title: Exceptions
sidebar_position: 20
description: The exceptions Dirthara I18n throws, what each one means, and the context it carries.
---

Every exception the package throws implements `Dirthara\I18n\Exception\I18nException`, so catching that interface
catches anything from the package. Each one carries a `context` array with the details of the failure, and wraps the
exception that caused it, such as a Flysystem, JSON, PHP, or Intl exception, as its previous exception. No Intl
exception or Intl error leaves the package unwrapped.

No message or context contains a translation. They can contain paths, locale codes, translation keys,
cache keys, and prefixes, with control characters escaped in the message.

| Exception                              | Extends                    | Thrown when                                             |
|----------------------------------------|----------------------------|---------------------------------------------------------|
| `InvalidLocaleException`               | `InvalidArgumentException` | A locale code is not a well-formed BCP 47 tag.          |
| `InvalidCurrencyException`             | `InvalidArgumentException` | A currency code is not three letters.                   |
| `InvalidTranslationPrefixException`    | `InvalidArgumentException` | A loader is given an invalid prefix.                    |
| `InvalidTranslationCatalogueException` | `InvalidArgumentException` | A catalogue is given a message that is not a string.    |
| `TranslationLoaderException`           | `RuntimeException`         | A translation source cannot be read or is malformed.    |
| `TranslationCacheException`            | `RuntimeException`         | A cache entry cannot be written, read, or removed, or is malformed. |
| `InvalidPluralCountException`          | `InvalidArgumentException` | A plural count is `NAN`, `INF`, or `-INF`. Its context holds `locale` and `count`. |
| `PluralRulesException`                 | `RuntimeException`         | Intl cannot create the plural rules for a locale, fails to give a plural category, or gives one the package does not know. |
| `InvalidCurrencyAmountException`       | `InvalidArgumentException` | An amount to format is `NAN`, `INF`, or `-INF`. Its context holds `locale`, `currency`, and `amount`. |
| `CurrencyFormatterException`           | `RuntimeException`         | Intl cannot create a currency formatter for a locale and style, or fails to format an amount. |
| `InvalidNumberException`               | `InvalidArgumentException` | A number to format is `NAN`, `INF`, or `-INF`, or an ordinal is not a whole number. |
| `FormatterException`                   | `RuntimeException`         | Intl cannot create a formatter, or fails to format a value, in any formatter but the currency formatter. |

## Loader failures

| Failure                                                            | Context                     |
|--------------------------------------------------------------------|-----------------------------|
| The configured source directory does not exist.                    | `path`                      |
| A file or directory cannot be read through Flysystem.              | `path`                      |
| A PHP file fails to parse, throws, or raises an error.             | `path`                      |
| A PHP file does not return an array.                               | `path`                      |
| A JSON file is not valid JSON.                                     | `path`                      |
| A JSON file is not an object.                                      | `path`                      |
| A translation is not a string or a group of translations.          | `path`, `key`               |
| A JSON key is empty or a decimal integer.                          | `path`, `key`               |
| Two translations produce the same key.                             | `path`, `key`               |
| The `dirthara-i18n://` stream wrapper cannot be registered.        | `scheme`                    |
| A loader returns a catalogue for another locale than requested.    | `locale`, `loadedLocale`, and `loader` when combined |
| Two combined loaders produce the same key.                         | `locale`, `key`, `loaders`  |

A catalogue with an empty or decimal integer key, or a message that is not a string, throws an
`InvalidTranslationCatalogueException` with `locale` and `key` in its context. When the catalogue comes from a cache
entry, the entry's `path` and `cacheKey` are added to that context.

## Cache failures

| Failure                                                            | Context                                |
|--------------------------------------------------------------------|----------------------------------------|
| The cache directory does not exist and cannot be created.          | `path`                                 |
| An entry cannot be written to its temporary file.                  | `path`, `cacheKey`, `locale`           |
| An entry cannot be moved into place.                               | `path`, `cacheKey`, `locale`           |
| An entry cannot be read or does not parse.                         | `path`, `cacheKey`, `locale`           |
| An entry does not return an array.                                 | `path`, `cacheKey`, `locale`           |
| An entry cannot be removed.                                        | `path`, `cacheKey`, `locale`           |
| The entries of a cache key cannot all be listed or removed.        | `path`, `cacheKey`                     |

`key` is always a translation key and `cacheKey` always a cache key.

## Plural rule failures

| Failure                                                            | Context                                         |
|--------------------------------------------------------------------|-------------------------------------------------|
| Intl cannot create the plural rules, such as for a locale code longer than the 156 characters `intl` accepts. | `locale`; the `IntlException` is the previous exception |
| ICU fails to format a count into a plural category.                | `locale`, `count`, `intlCode`, `intlMessage`    |
| ICU gives a category that is not a known `PluralCategory`.         | `locale`, `count`, `category`                   |

## Currency formatter failures

| Failure                                                            | Context                                                    |
|--------------------------------------------------------------------|------------------------------------------------------------|
| Intl cannot create the formatter, such as for a locale code longer than the 156 characters `intl` accepts. | `locale`, `style`; the `IntlException` is the previous exception |
| Intl fails to format an amount.                                    | `locale`, `currency`, `style`, `intlCode`, `intlMessage`   |

The amount itself is not part of an exception's message or context, unless it is not a finite number.

## Formatter failures

`FormatterException` and `InvalidNumberException` carry the name of the formatter, such as `number`, in `formatter`.

| Failure                                                            | Exception                 | Context                                                      |
|--------------------------------------------------------------------|---------------------------|--------------------------------------------------------------|
| Intl cannot create a formatter, such as for a locale code longer than the 156 characters `intl` accepts. | `FormatterException` | `formatter`, `locale`, `style`; the `IntlException` is the previous exception |
| Intl fails to format a value.                                      | `FormatterException`      | `formatter`, `locale`, `style`, `intlCode`, `intlMessage`    |
| A number is `NAN`, `INF`, or `-INF`.                                | `InvalidNumberException`  | `formatter`, `locale`, `number`                              |
| An ordinal is not a whole number.                                  | `InvalidNumberException`  | `formatter`, `locale`, `number`                              |
