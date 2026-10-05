<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract\Factory;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\PercentageFormatter;

interface PercentageFormatterFactory
{
    public function create(Locale $locale): PercentageFormatter;
}
