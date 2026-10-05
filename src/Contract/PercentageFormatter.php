<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;

interface PercentageFormatter
{
    public Locale $locale { get; }

    public function format(int|float $value): string;
}
