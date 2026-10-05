<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use DateTimeZone;
use DateTimeInterface;
use Dirthara\I18n\Locale;
use Dirthara\I18n\DateTimeStyle;

interface DateTimeFormatter
{
    public Locale $locale { get; }

    public DateTimeZone $timezone { get; }

    public function format(
        DateTimeInterface $dateTime,
        DateTimeStyle $dateStyle = DateTimeStyle::Medium,
        DateTimeStyle $timeStyle = DateTimeStyle::None,
    ): string;
}
