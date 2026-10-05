<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\NumberStyle;

interface NumberFormatter
{
    public Locale $locale { get; }

    public function format(int|float $number): string;

    public function compact(int|float $number, NumberStyle $style = NumberStyle::Short): string;

    public function scientific(int|float $number): string;

    public function spellOut(int|float $number): string;

    public function ordinal(int|float $number): string;
}
