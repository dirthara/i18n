<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Number;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\Factory\NumberFormatterFactory as NumberFormatterFactoryContract;

final readonly class NumberFormatterFactory implements NumberFormatterFactoryContract
{
    public function create(Locale $locale): NumberFormatter
    {
        return new NumberFormatter($locale);
    }
}
