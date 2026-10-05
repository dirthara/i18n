<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\ListType;
use Dirthara\I18n\Enum\ListWidth;
use Dirthara\I18n\Exception\I18nException;

interface ListFormatter
{
    public Locale $locale { get; }

    /**
     * @param array<array-key, string> $items
     *
     * @throws I18nException
     */
    public function format(array $items, ListType $type = ListType::And, ListWidth $width = ListWidth::Wide): string;
}
