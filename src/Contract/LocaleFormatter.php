<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;

interface LocaleFormatter
{
    public Locale $locale { get; }

    public function format(Locale $locale): string;

    public function language(Locale $locale): string;

    public function region(Locale $locale): ?string;

    public function script(Locale $locale): ?string;

    /**
     * @return list<string>
     */
    public function variants(Locale $locale): array;
}
