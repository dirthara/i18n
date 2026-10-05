<?php

namespace Dirthara\I18n\Contract;

use DateTimeInterface;
use DateTimeZone;
use Dirthara\I18n\Enum\RelativeDateTimeStyle;
use Dirthara\I18n\Locale;

interface RelativeDateTimeFormatter
{
    public Locale $locale { get; }

    public DateTimeZone $timezone { get; }

    public function format(
        DateTimeInterface $dateTime,
        DateTimeInterface $relativeTo,
        RelativeDateTimeStyle $style = RelativeDateTimeStyle::Long,
    ): string;
}