<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use DateTimeZone;
use DateTimeInterface;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\DateStyle;
use Dirthara\I18n\Enum\TimeStyle;
use Dirthara\I18n\Exception\I18nException;

interface DateTimeFormatter
{
    public Locale $locale { get; }

    public DateTimeZone $timezone { get; }

    /**
     * @throws I18nException
     */
    public function format(
        DateTimeInterface $dateTime,
        DateStyle $dateStyle = DateStyle::Medium,
        TimeStyle $timeStyle = TimeStyle::None,
    ): string;

    /**
     * @throws I18nException
     */
    public function formatDate(DateTimeInterface $dateTime, DateStyle $dateStyle = DateStyle::Medium): string;

    /**
     * @throws I18nException
     */
    public function formatTime(DateTimeInterface $dateTime, TimeStyle $timeStyle = TimeStyle::Medium): string;
}
