<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;

interface CurrencyFormatter
{
    public Locale $locale { get; }

    public function format(int|float $amount, string $currency): string;
}
