<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Currency;
use Dirthara\I18n\Enum\CurrencyStyle;

interface CurrencyFormatter
{
    public Locale $locale { get; }

    public function format(
        int|float $amount,
        Currency $currency,
        CurrencyStyle $style = CurrencyStyle::Standard,
    ): string;
}
