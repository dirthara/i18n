<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Exception\I18nException;
use Dirthara\I18n\Translation\TranslationCatalogue;

interface TranslationLoader
{
    /**
     * @throws I18nException
     */
    public function load(Locale $locale): TranslationCatalogue;
}
