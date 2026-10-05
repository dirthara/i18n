<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\List;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\Factory\ListFormatterFactory as ListFormatterFactoryContract;

final readonly class ListFormatterFactory implements ListFormatterFactoryContract
{
    public function create(Locale $locale): ListFormatter
    {
        return new ListFormatter($locale);
    }
}
