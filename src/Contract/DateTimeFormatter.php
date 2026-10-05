<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use DateTimeZone;
use DateTimeInterface;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\DateStyle;
use Dirthara\I18n\Enum\TimeStyle;

interface DateTimeFormatter
{
    public Locale $locale { get; }

    public DateTimeZone $timezone { get; }

    public function format(
        DateTimeInterface $dateTime,
        DateStyle $dateStyle = DateStyle::Medium,
        TimeStyle $timeStyle = TimeStyle::None,
    ): string;

    public function formatDate(DateTimeInterface $dateTime, DateStyle $dateStyle = DateStyle::Medium): string;

    public function formatTime(DateTimeInterface $dateTime, TimeStyle $timeStyle): string;
}
