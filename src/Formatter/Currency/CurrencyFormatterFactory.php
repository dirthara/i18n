<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Currency;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\Factory\CurrencyFormatterFactory as CurrencyFormatterFactoryContract;

final readonly class CurrencyFormatterFactory implements CurrencyFormatterFactoryContract
{
    public function create(Locale $locale): CurrencyFormatter
    {
        return new CurrencyFormatter($locale);
    }
}
