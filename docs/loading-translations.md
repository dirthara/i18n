---
id: loading-translations
title: Loading translations
sidebar_position: 3
description: Translation catalogues, the PHP, JSON, and database loaders, key prefixes, and writing a custom loader.
---

A loader reads the translations of one locale from one configured source and returns them as a
`Dirthara\I18n\Translation\TranslationCatalogue`. Every loader implements `Dirthara\I18n\Contract\TranslationLoader`:

```php
interface TranslationLoader
{
    public function load(Locale $locale): TranslationCatalogue;
}
```

A loader does not fall back to another locale. Loading `nl-NL` reads what the source has for `nl-NL` and nothing else:
not `nl`, and not a default locale. An empty catalogue means the source exists and has nothing for that locale; a
source that does not exist, cannot be read, or holds something that is not a translation throws a
`TranslationLoaderException`. See [source availability](#source-availability).

## Catalogues

A catalogue holds the translations of exactly one locale, by key.

```php
use Dirthara\I18n\Locale;
use Dirthara\I18n\Translation\TranslationCatalogue;

$catalogue = new TranslationCatalogue(new Locale('en-GB'), [
    'validation.required' => 'The {attribute} field is required.',
]);

$catalogue->locale;                         // Locale en-GB
$catalogue->messages;                       // ['validation.required' => 'The {attribute} field is required.']
$catalogue->has('validation.required');     // true
$catalogue->get('validation.required');     // 'The {attribute} field is required.'
$catalogue->get('validation');              // null
```

`has()` and `get()` match the exact key. There is no fallback, no partial matching, and no locale matching in a
catalogue. `$messages` is an `array<string, string>`: a key that breaks the [key rules](#keys), or a message that is
not a string, throws an `InvalidTranslationCatalogueException`. A catalogue does not know which source its messages
came from.

## Translation source rules

These rules hold for every loader, for catalogues built by hand, and for the cache.

### Keys

A translation key is a string that is not empty. It can contain spaces and punctuation, such as `Log out` or
`Are you sure?`, and it does not have to use dots.

A key that is a bare decimal integer, such as `404`, `0`, or `-1`, is not supported. PHP turns such a string into an
`int` when it is used as an array key, so it cannot stay a string key in a catalogue. A loader that would produce one
throws a `TranslationLoaderException`. Namespace the key instead, such as `errors.404` or `http.500`, or give the loader
a prefix, which turns a source key `404` into `errors.404`. Keys such as `404.message`, `007`, and `1.5` are fine,
because PHP keeps them as strings.

### Duplicate JSON keys

A JSON translation file that has the same key twice in one object is invalid. Dirthara 0.1 uses PHP's own JSON decoder,
which does not report duplicate keys, and does not detect them itself. Make sure every key in a JSON file is unique:
which of two equal keys takes effect is not defined, and may change.

### Source availability

| Situation                                                      | Result                          |
|----------------------------------------------------------------|---------------------------------|
| The configured source exists and has no translations for the locale. | An empty catalogue.       |
| The configured source does not exist, cannot be read, or is malformed. | A `TranslationLoaderException`. |

For the PHP and JSON loaders, the source is the configured `path` directory, which has to exist: a typo such as
`translatons` throws rather than reading as a source without translations. A loader whose `path` is the root of the
filesystem (`''` or `/`) does not check the root, because some Flysystem adapters do not report an empty root as an
existing directory. For the database loader, the source is the table: a table without rows for the locale gives an
empty catalogue, and a missing table throws.

### Conflicts between loaders

Within one source, a loader rejects a key it produces twice, where it can detect it: two PHP translations that flatten
to the same key, or two database rows for the same locale and key. Two different loaders can still produce the same key
for one locale, such as an application and a package that both define `validation.required`.

Combining the catalogues of several loaders is not implemented yet. When it is, it follows these rules:

- **By default, a key that two loaders produce is an error.**
- **Overriding is opt-in.** One loader's translation replaces another's only when the caller asks for an override
  policy.
- **Precedence comes from the order the caller gives the loaders in.** It never depends on the order files are listed
  in or rows are returned in.
- **A catalogue stays unaware of where its messages came from.** Combining happens outside it.

## Prefixes

The PHP, JSON, and database loaders take an optional prefix, which namespaces every key they load. A package can use
one to keep its keys apart from an application's.

| Prefix                | Key in the source     | Key in the catalogue           |
|-----------------------|-----------------------|--------------------------------|
| `null`                | `validation.required` | `validation.required`          |
| `dirthara`            | `validation.required` | `dirthara.validation.required` |
| `dirthara.validation` | `required`            | `dirthara.validation.required` |

`null` means no prefix. A prefix is one or more segments separated by single dots, so the empty string, a prefix that
starts or ends with a dot, and a prefix with two dots in a row are rejected with an
`InvalidTranslationPrefixException` when the loader is constructed. That keeps a prefix from producing a key with an
empty segment.

## PHP files

`Dirthara\I18n\Translation\PhpTranslationLoader` reads PHP files from any Flysystem filesystem, local or remote.

```php
use Dirthara\I18n\Translation\PhpTranslationLoader;

$loader = new PhpTranslationLoader(
    filesystem: $filesystem,
    path: 'translations',
    prefix: null,
);
```

| Option       | Type                                | Default | Meaning                                                  |
|--------------|-------------------------------------|---------|----------------------------------------------------------|
| `filesystem` | `League\Flysystem\FilesystemReader` |         | The filesystem the translations are on.                  |
| `path`       | `string`                            |         | The directory that holds a directory per locale. `''` is the root of the filesystem. |
| `prefix`     | `?string`                           | `null`  | The prefix for every key.                                |

Each locale has a directory named after its canonical code, which is how `Locale` writes it: `en-GB`, not `en_gb`.

```text
translations/
    en-GB/
        validation.php
        messages.php
    nl-NL/
        validation.php
        messages.php
```

Only the `.php` files directly in the locale's directory are loaded. Other files and subdirectories are ignored. A
locale without a directory gives an empty catalogue, but a `path` that does not exist throws. The files are loaded in the order of their paths, whatever order
the filesystem lists them in.

A file returns an array. Its file name is the first segment of every key in it, and nested arrays add a segment each:

```php
<?php

return [
    'required' => 'The {attribute} field is required.',
    'between' => [
        'numeric' => 'The {attribute} must be between {min} and {max}.',
    ],
];
```

As `validation.php`, this gives `validation.required` and `validation.between.numeric`; with the prefix `dirthara`,
`dirthara.validation.required` and `dirthara.validation.between.numeric`.

Nested arrays only group translations. Every value that is not an array has to be a string: an integer, a float, a
boolean, `null`, or an object is rejected rather than converted. Two translations that produce the same key, such as
`'between' => ['numeric' => ...]` next to `'between.numeric' => ...`, or a key in `validation.php` that
`validation.between.php` also produces, are rejected as well, rather than one silently replacing the other.

### How a PHP file runs

A PHP translation file is run, not parsed, so it can use constants and expressions. The loader reads the source through
Flysystem and serves it to `require` through a `dirthara-i18n://` stream wrapper, instead of copying it to a temporary
file. Nothing is written to the local disk, and `__FILE__` in a translation file is its virtual path, such as
`dirthara-i18n://translations/en-GB/validation.php`. A file that fails to parse, throws, or does not return an array is
reported with its path.

:::caution
A PHP translation file is code, and loading it runs that code. Load PHP translations only from a source you trust as
much as the application itself. Use JSON or the database for translations from anywhere else.
:::

## JSON files

`Dirthara\I18n\Translation\JsonTranslationLoader` reads one JSON file per locale from any Flysystem filesystem. It takes
the same options as the PHP loader.

```php
use Dirthara\I18n\Translation\JsonTranslationLoader;

$loader = new JsonTranslationLoader(
    filesystem: $filesystem,
    path: 'translations',
    prefix: 'application',
);
```

```text
translations/
    en-GB.json
    nl-NL.json
```

A locale without a file gives an empty catalogue, but a `path` that does not exist throws. The file is an object whose keys are the translation keys and whose
values are the translations:

```json
{
    "Welcome": "Welcome",
    "Log out": "Log out"
}
```

The keys are used as they are, so a key can be a whole sentence. With the prefix `application`, these become
`application.Welcome` and `application.Log out`. JSON is not flattened: a value that is an object, an array, `null`, a
boolean, or a number is rejected. A file that is not valid JSON, or whose top level is not an object, is rejected too.
Keys have to be unique; see [duplicate JSON keys](#duplicate-json-keys).

## Database

`Dirthara\I18n\Translation\DatabaseTranslationLoader` reads translations from a table through
[`dirthara/database`](https://github.com/dirthara/database).

```php
use Dirthara\I18n\Translation\DatabaseTranslationLoader;

$loader = new DatabaseTranslationLoader(
    database: $database->using(),
    table: 'translations',
    prefix: null,
);
```

| Option     | Type                                  | Default          | Meaning                                  |
|------------|---------------------------------------|------------------|------------------------------------------|
| `database` | `Dirthara\Database\ConnectedDatabase` |                  | The connection the table is on.          |
| `table`    | `string`                              | `'translations'` | The table to read.                       |
| `prefix`   | `?string`                             | `null`           | The prefix for every key.                |

The table needs these columns. Others, such as an `id` or timestamps, are allowed and ignored.

| Column        | Holds                                                    |
|---------------|----------------------------------------------------------|
| `locale`      | The canonical locale code, such as `nl-NL`.              |
| `key`         | The translation key.                                     |
| `translation` | The translated message.                                  |

The loader only reads. It does not create, migrate, or change the table, so the application has to create it, for
example with a migration. It reads the rows whose `locale` is exactly the requested locale's code: `nl-NL` does not
read `nl`, `nl-nl`, or `nl_NL` rows, even when the column's collation would match them.

Every `key` and `translation` has to be a string; a `NULL` or a number is rejected rather than converted, and a key has
to follow the [key rules](#keys) once the prefix is applied. A key that appears in two rows for the same locale is rejected too, instead of letting the order the database returns rows in
decide which one wins. A failure in the database, such as a missing table, is reported as a
`TranslationLoaderException` with the database's exception as its previous exception, so a caller never has to catch a
`dirthara/database` exception. Neither the message nor the context of an exception contains a translation.

## Custom loaders

Anything that implements `TranslationLoader` is a loader, so a package can load translations from any other source,
such as an API or another file format. A custom loader returns a catalogue for exactly the locale it was asked for,
returns an empty catalogue when the source has nothing for that locale, and throws an exception that implements
`Dirthara\I18n\Exception\I18nException` when the source cannot be read. Any loader can be cached; see
[Caching translations](caching-translations.md).
