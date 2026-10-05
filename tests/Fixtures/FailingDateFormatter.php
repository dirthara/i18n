<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use IntlDateFormatter;

final class FailingDateFormatter extends IntlDateFormatter
{
    public function format(mixed $datetime): string|false
    {
        return false;
    }
}
