<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Percentage;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\Factory\PercentageFormatterFactory as PercentageFormatterFactoryContract;

final readonly class PercentageFormatterFactory implements PercentageFormatterFactoryContract
{
    public function create(Locale $locale): PercentageFormatter
    {
        return new PercentageFormatter($locale);
    }
}
