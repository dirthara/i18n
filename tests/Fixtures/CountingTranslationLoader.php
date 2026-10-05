<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\TranslationLoader;
use Dirthara\I18n\Translation\TranslationCatalogue;

final class CountingTranslationLoader implements TranslationLoader
{
    public int $loads = 0;

    /**
     * @param array<string, array<string, string>> $messages Messages by locale code.
     */
    public function __construct(
        private readonly array $messages = [],
        private readonly ?Locale $answerWith = null,
    ) {}

    public function load(Locale $locale): TranslationCatalogue
    {
        $this->loads++;

        return new TranslationCatalogue($this->answerWith ?? $locale, $this->messages[$locale->code] ?? []);
    }
}
