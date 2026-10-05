<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Enum\DurationStyle;
use Dirthara\I18n\Locale;

interface DurationFormatter
{
    public Locale $locale { get; }

    public function format(int|float $seconds, DurationStyle $style = DurationStyle::Long): string;
}
