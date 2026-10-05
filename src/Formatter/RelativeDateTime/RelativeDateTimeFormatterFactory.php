<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\RelativeDateTime;

use DateTimeZone;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\Factory\RelativeDateTimeFormatterFactory as RelativeDateTimeFormatterFactoryContract;

final readonly class RelativeDateTimeFormatterFactory implements RelativeDateTimeFormatterFactoryContract
{
    public function create(Locale $locale, DateTimeZone $timezone): RelativeDateTimeFormatter
    {
        return new RelativeDateTimeFormatter($locale, $timezone);
    }
}
