---
id: caching-translations
title: Caching translations
sidebar_position: 5
description: The compiled PHP translation cache, cache keys, its file format, and invalidating it.
---

Loading translations from their source on every request means scanning directories, running PHP files, decoding JSON,
or querying another source, and then flattening, checking, and prefixing the result again. The translation cache does
that once and stores the finished catalogue as a plain PHP file, so later requests only include that file.

## Caching a loader

Caching is not part of any loader. `Dirthara\I18n\Translation\CachedTranslationLoader` wraps any `TranslationLoader`
and is a loader itself, so the PHP and JSON loaders, combined loaders, and any custom loader are cached the same way:

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
When anything changes that affects the keys or messages a loader produces, such as its path, prefix,
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

:::danger
The translation cache directory is executable application state. Reading an entry runs it as PHP, before the cache
sees what it returns, so neither `CacheEntryValidation::Validate` nor `CacheEntryValidation::Trust` makes a file
someone else wrote safe: validation only checks the array a file returns. Only trusted application processes and
users may write to the directory.
:::

The cache creates its directories with mode `0755`, less whatever the umask removes, and gives every entry mode
`0644` before it moves into place, whatever the umask: only the owner can write either. A directory that already exists
keeps its own mode, so give it no more access than that.

| Option       | Type                                     | Default                          | Meaning                       |
|--------------|------------------------------------------|----------------------------------|-------------------------------|
| `path`       | `string`                                 |                                  | The local cache directory.    |
| `validation` | `Dirthara\I18n\Enum\CacheEntryValidation` | `CacheEntryValidation::Validate` | Whether an entry's keys and messages are checked when it is read. |

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

A missing file is a miss. By default, an existing file is included and checked once, by the catalogue it becomes, even
though the cache wrote it: its keys have to follow the [key rules](loading-translations.md#keys) and its values have to be strings.
A file that breaks those rules throws the catalogue's `InvalidTranslationCatalogueException`, with the file's `path`
and `cacheKey` added to its context. A file that does not return an array, does not parse, or cannot be read throws a
`TranslationCacheException`. The cached loader does not
fall back to its source when that happens, because a corrupt cache is an operational fault that should be seen, not
repaired silently on every request.

### Trusting entries

Checking an entry is the only work left in a cache hit once OPcache serves the file: it visits every key and message on
every request. `CacheEntryValidation::Trust` skips it and uses the array the file returns as it is, which makes a hit
close to free; for 5,000 translations, it takes a request from about 0.3 ms to a few microseconds.

```php
use Dirthara\I18n\Enum\CacheEntryValidation;

$cache = new PhpTranslationCache(
    path: '/application/cache/translations',
    validation: CacheEntryValidation::Trust,
);
```

A trusted entry that does not return an array, does not parse, or cannot be read still throws a
`TranslationCacheException`. Its keys and messages are not checked, though. Skipping the check is a setting of the
cache only: a catalogue an application builds itself always goes through the constructor, which checks it.

:::caution
With `Trust`, an entry that was edited by hand or damaged, and holds an invalid key or a message that is not a string,
is used as it is instead of failing. Trust the cache only when nothing but the cache writes to its directory, and
forget or rebuild the entries when in doubt. Every entry the cache writes itself comes from a checked catalogue.
:::

## Invalidation

The cache never expires anything and never checks a source for changes. There is no time to live, and changing a PHP
file, a JSON file, or the data behind a custom loader does not invalidate anything. Not every source has a
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

## OPcache

OPcache keeps the compiled version of a cache file by its path, so whether a replaced or removed entry is noticed depends
on how OPcache is configured. The package never resets or invalidates OPcache itself; refreshing it is part of
deploying or clearing translations.

| Setting                          | Default | Effect on cache entries                                                      |
|----------------------------------|---------|------------------------------------------------------------------------------|
| `opcache.validate_timestamps`    | `1`     | `0` never checks files again: a replaced or removed entry is served from OPcache until OPcache is reset or PHP restarts. |
| `opcache.revalidate_freq`        | `2`     | With timestamps validated, a file is checked at most once per this many seconds, so a replaced entry can be served in its old form for that long. `0` checks on every request. |
| `opcache.file_update_protection` | `2`     | A file modified less than this many seconds before a request started is not cached, so a new entry is read and parsed until it is that old. |

A forgotten or rewritten entry is therefore not necessarily seen at once: with the defaults, for up to about two seconds,
and with `opcache.validate_timestamps` turned off, until OPcache is reset. Where translations have to change at a
precise moment, reset OPcache, or reload PHP, as part of clearing or rebuilding the translation cache. Measure the
cache's speed only after it has warmed up past `opcache.file_update_protection`.
