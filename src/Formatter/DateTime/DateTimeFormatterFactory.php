<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\DateTime;

use DateTimeZone;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\Factory\DateTimeFormatterFactory as DateTimeFormatterFactoryContract;

final readonly class DateTimeFormatterFactory implements DateTimeFormatterFactoryContract
{
    public function create(Locale $locale, DateTimeZone $timezone): DateTimeFormatter
    {
        return new DateTimeFormatter($locale, $timezone);
    }
}
