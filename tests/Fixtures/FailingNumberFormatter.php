<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use NumberFormatter;

final class FailingNumberFormatter extends NumberFormatter
{
    public function format(int|float $num, int $type = NumberFormatter::TYPE_DEFAULT): string|false
    {
        return false;
    }

    public function formatCurrency(float $amount, string $currency): string|false
    {
        return false;
    }
}
