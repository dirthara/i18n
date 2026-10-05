<?php

declare(strict_types=1);

namespace Dirthara\I18n\Enum;

enum DateStyle
{
    case None;
    case Short;
    case Medium;
    case Long;
    case Full;
    case RelativeShort;
    case RelativeMedium;
    case RelativeLong;
    case RelativeFull;
}
