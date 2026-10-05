<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Money;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\CurrencyStyle;

interface CurrencyFormatter
{
    public Locale $locale { get; }

    public function format(Money $money, CurrencyStyle $style = CurrencyStyle::Standard): string;
}
