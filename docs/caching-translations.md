---
id: caching-translations
title: Caching translations
sidebar_position: 4
description: The compiled PHP translation cache, its file format, and invalidating it.
---

Loading translations from their source on every request means scanning directories, running PHP files, decoding JSON,
or querying the database, and then flattening, checking, and prefixing the result again. The translation cache does
that once and stores the finished catalogue as a plain PHP file, so later requests only include that file.

## Caching a loader

Caching is not part of any loader. `Dirthara\I18n\Translation\CachedTranslationLoader` wraps any `TranslationLoader`
and is a loader itself, so the PHP, JSON, and database loaders, and any custom loader, are cached the same way:

```php
use Dirthara\I18n\Translation\PhpTranslationCache;
use Dirthara\I18n\Translation\PhpTranslationLoader;
use Dirthara\I18n\Translation\CachedTranslationLoader;

$cache = new PhpTranslationCache(path: '/application/cache/translations');

$loader = new CachedTranslationLoader(
    loader: new PhpTranslationLoader($filesystem, 'translations', 'dirthara.validation'),
    cache: $cache,
    key: 'validation',
);

$catalogue = $loader->load(new Locale('en-GB'));
```

| Option   | Type                                    | Meaning                                                    |
|----------|-----------------------------------------|------------------------------------------------------------|
| `loader` | `Dirthara\I18n\Contract\TranslationLoader` | The loader to cache.                                    |
| `cache`  | `Dirthara\I18n\Contract\TranslationCache`  | Where the catalogues are stored.                        |
| `key`    | `string`                                | Identifies the wrapped loader's source in the cache.      |

`load()` looks for an entry for its key and the locale. If there is one, it returns it without calling the wrapped
loader. If there is none, it calls the wrapped loader, stores the catalogue, and returns it.

The key is what tells two sources apart: an application can have an `application`, a `validation`, and an
`authorisation` loader for the same locale, and each needs its own key or they overwrite each other's entries. Choose a
key that stays the same between requests and deployments. It is never derived from the loader object, so two loaders
with the same key share their entries.

An empty catalogue is cached as well. A locale a source has no translations for is a hit from then on, instead of a
directory scan or a query on every request.

## The PHP cache

`Dirthara\I18n\Translation\PhpTranslationCache` implements `Dirthara\I18n\Contract\TranslationCache`, which has three
methods:

```php
interface TranslationCache
{
    public function get(string $key, Locale $locale): ?TranslationCatalogue;

    public function put(string $key, TranslationCatalogue $catalogue): void;

    public function forget(string $key, Locale $locale): void;
}
```

The cache writes to a local directory, given as `path`. It creates the directory, and any missing parents, when it
first writes an entry. It does not use Flysystem: a cache file is a local runtime artefact that PHP includes directly,
so OPcache can keep it compiled.

Each entry is one file, named after a SHA-256 hash of the key and the locale's code, such as
`9e4f...c1.en-GB.php`. Because the key is hashed, no key, however it is written, can name a file outside the directory.
The file returns the catalogue's messages and nothing else, sorted by key so that the same messages always give the
same file:

```php
<?php

declare(strict_types=1);

return array (
  'dirthara.validation.between.numeric' => 'The {attribute} must be between {min} and {max}.',
  'dirthara.validation.required' => 'The {attribute} field is required.',
);
```

There is no locale, prefix, source path, loader, or object in it. The key and the locale are in the file name, and the
prefix is already part of every key.

### Writing an entry

An entry is written to a temporary file in the cache directory and then renamed over the entry's file. A rename on one
filesystem is atomic, so a request that reads an entry while another writes it gets the old file or the new one, never
part of one; two processes that write the same entry at once each rename a complete file. A write that fails removes
its temporary file and throws a `TranslationCacheException`.

### Reading an entry

A missing file is a miss. An existing file is included and checked even though the cache wrote it: it has to return an
array whose values are all strings. A file that does not, that does not parse, or that cannot be read throws a
`TranslationCacheException`. The cached loader does not fall back to its source when that happens, because a corrupt
cache is an operational fault that should be seen, not repaired silently on every request.

## Invalidation

The cache never expires anything and never checks a source for changes. There is no time to live, and changing a PHP
file, a JSON file, or a row in the translations table does not invalidate anything. Database translations have no
modification time to compare, and checking every source on every request would cost what the cache saves.

When translations change, the application clears or rebuilds the entries for them. `forget()` removes one entry: the
one for a key and a locale. The next load for that key and locale reads the source again and stores the result.

```php
$cache->forget('validation', new Locale('en-GB'));
```

:::caution
With `opcache.validate_timestamps` turned off, OPcache keeps serving the compiled version of a cache file that has
been replaced or removed until OPcache is reset. Reset OPcache, or reload PHP, whenever you clear or rebuild the
translation cache in such an environment.
:::
