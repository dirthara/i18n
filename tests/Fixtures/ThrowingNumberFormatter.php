<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use IntlException;
use NumberFormatter;

final class ThrowingNumberFormatter extends NumberFormatter
{
    public function format(int|float $num, int $type = NumberFormatter::TYPE_DEFAULT): string|false
    {
        throw new IntlException('Number formatting failed.');
    }

    public function formatCurrency(float $amount, string $currency): string|false
    {
        throw new IntlException('Currency formatting failed.');
    }
}
