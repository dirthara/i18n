<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use DateTimeZone;
use DateTimeInterface;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\RelativeDateTimeStyle;

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
