<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\ListType;
use Dirthara\I18n\Enum\ListWidth;

interface ListFormatter
{
    public Locale $locale { get; }

    /**
     * @param list<string> $items
     */
    public function format(array $items, ListType $type = ListType::And, ListWidth $width = ListWidth::Wide): string;
}
