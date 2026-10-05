<?php

declare(strict_types=1);

namespace Dirthara\I18n\Enum;

enum CacheEntryValidation: string
{
    case Validate = 'validate';
    case Trust = 'trust';
}
