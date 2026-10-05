<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\List;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Formatter\List\ListFormatter;
use Dirthara\I18n\Formatter\List\ListFormatterFactory;
use Dirthara\I18n\Contract\Factory\ListFormatterFactory as ListFormatterFactoryContract;

final class ListFormatterFactoryTest extends TestCase
{
    #[Test]
    public function it_implements_the_list_formatter_factory_contract(): void
    {
        self::assertInstanceOf(ListFormatterFactoryContract::class, new ListFormatterFactory());
    }

    #[Test]
    public function it_creates_a_list_formatter_for_a_locale(): void
    {
        $locale = new Locale('de-DE');

        $formatter = new ListFormatterFactory()->create($locale);

        self::assertInstanceOf(ListFormatter::class, $formatter);
        self::assertSame($locale, $formatter->locale);
        self::assertSame('a, b und c', $formatter->format(['a', 'b', 'c']));
    }
}
