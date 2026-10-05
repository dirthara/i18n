<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Duration;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\Factory\DurationFormatterFactory as DurationFormatterFactoryContract;

final readonly class DurationFormatterFactory implements DurationFormatterFactoryContract
{
    public function create(Locale $locale): DurationFormatter
    {
        return new DurationFormatter($locale);
    }
}
