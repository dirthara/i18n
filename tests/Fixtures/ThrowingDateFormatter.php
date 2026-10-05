<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use IntlException;
use IntlDateFormatter;

final class ThrowingDateFormatter extends IntlDateFormatter
{
    public function format(mixed $datetime): string|false
    {
        throw new IntlException('Date formatting failed.');
    }
}
