---
id: exceptions
title: Exceptions
sidebar_position: 5
description: The exceptions Dirthara I18n throws, what each one means, and the context it carries.
---

Every exception the package throws implements `Dirthara\I18n\Exception\I18nException`, so catching that interface
catches anything from the package. Each one carries a `context` array with the details of the failure, and wraps the
exception that caused it, such as a Flysystem, JSON, PHP, or `dirthara/database` exception, as its previous exception.

No message or context contains a translation. They can contain paths, locale codes, table names, translation keys,
cache keys, and prefixes, with control characters escaped in the message.

| Exception                              | Extends                    | Thrown when                                             |
|----------------------------------------|----------------------------|---------------------------------------------------------|
| `InvalidLocaleException`               | `InvalidArgumentException` | A locale code is not a well-formed BCP 47 tag.          |
| `InvalidCurrencyException`             | `InvalidArgumentException` | A currency code is not three letters.                   |
| `InvalidTranslationPrefixException`    | `InvalidArgumentException` | A loader is given an invalid prefix.                    |
| `InvalidTranslationCatalogueException` | `InvalidArgumentException` | A catalogue is given a message that is not a string.    |
| `TranslationLoaderException`           | `RuntimeException`         | A translation source cannot be read or is malformed.    |
| `TranslationCacheException`            | `RuntimeException`         | A cache entry cannot be written, read, or removed, or is malformed. |

## Loader failures

| Failure                                                       | Context                              |
|---------------------------------------------------------------|--------------------------------------|
| A file or directory cannot be read through Flysystem.        | `path`                               |
| A PHP file fails to parse, throws, or raises an error.       | `path`                               |
| A PHP file does not return an array.                          | `path`                               |
| A JSON file is not valid JSON.                                | `path`                               |
| A JSON file is not an object.                                 | `path`                               |
| A translation is not a string or a group of translations.     | `path`, `key`                        |
| Two translations produce the same key.                        | `path`, `key`                        |
| The `dirthara-i18n://` stream wrapper cannot be registered.  | `scheme`                             |
| A database query fails.                                       | `table`, `locale`                    |
| A database row has a `key` or `translation` that is not a string. | `table`, `locale`, `column`      |
| Two database rows for one locale have the same key.          | `table`, `locale`, `key`             |
| A cached loader's source returns a catalogue for another locale. | `locale`, `loadedLocale`          |

## Cache failures

| Failure                                                       | Context                              |
|---------------------------------------------------------------|--------------------------------------|
| The cache directory does not exist and cannot be created.    | `path`                               |
| An entry cannot be written to its temporary file.             | `path`, `key`, `locale`              |
| An entry cannot be moved into place.                          | `path`, `key`, `locale`              |
| An entry cannot be read or does not parse.                    | `path`, `key`, `locale`              |
| An entry does not return an array of strings.                 | `path`, `key`, `locale`              |
| An entry cannot be removed.                                   | `path`, `key`, `locale`              |
