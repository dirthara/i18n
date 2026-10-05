<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Translation\TranslationCatalogue;
use Dirthara\I18n\Exception\TranslationCacheException;
use Dirthara\I18n\Exception\InvalidTranslationCatalogueException;

interface TranslationCache
{
    /**
     * @throws TranslationCacheException
     * @throws InvalidTranslationCatalogueException
     */
    public function get(string $cacheKey, Locale $locale): ?TranslationCatalogue;

    /**
     * @throws TranslationCacheException
     */
    public function put(string $cacheKey, TranslationCatalogue $catalogue): void;

    /**
     * @throws TranslationCacheException
     */
    public function forget(string $cacheKey, Locale $locale): void;

    /**
     * @throws TranslationCacheException
     */
    public function forgetAll(string $cacheKey): void;
}
