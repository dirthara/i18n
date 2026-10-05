<?php

declare(strict_types=1);

namespace Dirthara\I18n\Enum;

enum CurrencyStyle
{
    case Standard;
    case Accounting;
    case Iso;
    case Name;
    case Cash;
}
