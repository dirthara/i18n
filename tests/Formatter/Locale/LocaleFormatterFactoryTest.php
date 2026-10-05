<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Locale;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Formatter\Locale\LocaleFormatter;
use Dirthara\I18n\Formatter\Locale\LocaleFormatterFactory;
use Dirthara\I18n\Contract\Factory\LocaleFormatterFactory as LocaleFormatterFactoryContract;

final class LocaleFormatterFactoryTest extends TestCase
{
    #[Test]
    public function it_implements_the_locale_formatter_factory_contract(): void
    {
        self::assertInstanceOf(LocaleFormatterFactoryContract::class, new LocaleFormatterFactory());
    }

    #[Test]
    public function it_creates_a_locale_formatter_for_a_locale(): void
    {
        $locale = new Locale('nl-NL');

        $formatter = new LocaleFormatterFactory()->create($locale);

        self::assertInstanceOf(LocaleFormatter::class, $formatter);
        self::assertSame($locale, $formatter->locale);
        self::assertSame('Engels', $formatter->format(new Locale('en')));
    }
}
