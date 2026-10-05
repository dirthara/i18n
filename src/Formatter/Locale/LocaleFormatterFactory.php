<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Locale;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\Factory\LocaleFormatterFactory as LocaleFormatterFactoryContract;

final readonly class LocaleFormatterFactory implements LocaleFormatterFactoryContract
{
    public function create(Locale $locale): LocaleFormatter
    {
        return new LocaleFormatter($locale);
    }
}
