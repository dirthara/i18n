---
id: caching-translations
title: Caching translations
sidebar_position: 4
description: The compiled PHP translation cache, cache keys, its file format, and invalidating it.
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
    cacheKey: 'validation-translations-v1',
);

$catalogue = $loader->load(new Locale('en-GB'));
```

| Option     | Type                                       | Meaning                                              |
|------------|--------------------------------------------|------------------------------------------------------|
| `loader`   | `Dirthara\I18n\Contract\TranslationLoader` | The loader to cache.                                 |
| `cache`    | `Dirthara\I18n\Contract\TranslationCache`  | Where the catalogues are stored.                     |
| `cacheKey` | `string`                                   | The identity of the wrapped loader's configuration.  |

`load()` looks for an entry for its cache key and the locale. If there is one, it returns it without calling the
wrapped loader. If there is none, it calls the wrapped loader, stores the catalogue, and returns it.

An empty catalogue is cached as well. A locale a source has no translations for is a hit from then on, instead of a
directory scan or a query on every request.

## Cache keys

A cache key identifies one configured translation source: one loader with one configuration. It is not a friendly name.
Two loaders with the same cache key share their entries, and two loaders with different cache keys never do, so an
`application`, a `validation`, and an `authorisation` loader for the same locale each need their own.

The cache key is never derived from the loader, because the package cannot tell what a custom loader or a Flysystem
adapter depends on. Choose it yourself, and keep it the same between requests and deployments for as long as the
source's configuration stays the same.

:::caution
When anything changes that affects the keys or messages a loader produces, such as its path, prefix, table,
filesystem, or any setting of a custom loader, its cached entries are stale. Either forget every entry of the old
cache key with `forgetAll()`, or give the loader a new cache key, such as `validation-translations-v2`. Neither
happens by itself.
:::

## The PHP cache

`Dirthara\I18n\Translation\PhpTranslationCache` implements `Dirthara\I18n\Contract\TranslationCache`:

```php
interface TranslationCache
{
    public function get(string $cacheKey, Locale $locale): ?TranslationCatalogue;

    public function put(string $cacheKey, TranslationCatalogue $catalogue): void;

    public function forget(string $cacheKey, Locale $locale): void;

    public function forgetAll(string $cacheKey): void;
}
```

The cache writes to a local directory, given as `path`. It creates the directory, and any missing parents, when it
first writes an entry. It does not use Flysystem: a cache file is a local runtime artefact that PHP includes directly,
so OPcache can keep it compiled.

Each entry is one file, named after a SHA-256 hash of the cache key and the locale's code, such as
`9e4f...c1.en-GB.php`. Because the cache key is hashed, no cache key, however it is written, can name a file outside
the directory. The file returns the catalogue's messages and nothing else, as an `array<string, string>` sorted by key,
so that the same messages always give the same file:

```php
<?php

declare(strict_types=1);

return array (
  'dirthara.validation.between.numeric' => 'The {attribute} must be between {min} and {max}.',
  'dirthara.validation.required' => 'The {attribute} field is required.',
);
```

There is no locale, prefix, source path, loader, or object in it. The cache key and the locale are in the file name,
and the prefix is already part of every key.

### Writing an entry

An entry is written to a temporary file in the cache directory and then renamed over the entry's file. A rename on one
filesystem is atomic, so a request that reads an entry while another writes it gets the old file or the new one, never
part of one; two processes that write the same entry at once each rename a complete file. A write that fails removes
its temporary file and throws a `TranslationCacheException`.

### Reading an entry

A missing file is a miss. An existing file is included and checked even though the cache wrote it: it has to return an
array whose keys follow the [key rules](loading-translations.md#keys) and whose values are all strings. A file that
does not, that does not parse, or that cannot be read throws a `TranslationCacheException`. The cached loader does not
fall back to its source when that happens, because a corrupt cache is an operational fault that should be seen, not
repaired silently on every request.

## Invalidation

The cache never expires anything and never checks a source for changes. There is no time to live, and changing a PHP
file, a JSON file, or a row in the translations table does not invalidate anything. Database translations have no
modification time to compare, and checking every source on every request would cost what the cache saves.

When translations change, the application clears or rebuilds the entries for them:

| Method                          | Removes                                                        |
|---------------------------------|----------------------------------------------------------------|
| `forget($cacheKey, $locale)`    | The entry of one cache key for one locale.                     |
| `forgetAll($cacheKey)`          | The entries of one cache key for every locale it has cached. |

```php
$cache->forget('validation-translations-v1', new Locale('en-GB'));
$cache->forgetAll('validation-translations-v1');
```

`forgetAll()` finds the entries by their file names, so it does not need to know which locales were cached. It leaves
the entries of every other cache key, and the temporary files of writes still in progress, alone. Forgetting an entry
or a cache key that has nothing cached does nothing. The next load for a forgotten locale reads the source again and
stores the result.

:::caution
With `opcache.validate_timestamps` turned off, OPcache keeps serving the compiled version of a cache file that has
been replaced or removed until OPcache is reset. Reset OPcache, or reload PHP, whenever you clear or rebuild the
translation cache in such an environment.
:::
