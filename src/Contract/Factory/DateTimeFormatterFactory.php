<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract\Factory;

use DateTimeZone;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\DateTimeFormatter;

interface DateTimeFormatterFactory
{
    public function create(Locale $locale, DateTimeZone $timezone): DateTimeFormatter;
}
