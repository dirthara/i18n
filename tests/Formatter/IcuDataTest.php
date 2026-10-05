<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use Dirthara\I18n\Formatter\IcuData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

final class IcuDataTest extends TestCase
{
    /**
     * @param list<string> $path
     */
    #[Test]
    #[DataProvider('values')]
    public function it_reads_a_value_from_the_most_specific_bundle_that_has_it(
        string $locale,
        string $bundle,
        array $path,
        string $expected,
    ): void {
        self::assertSame($expected, new IcuData()->string(new Locale($locale), $bundle, ...$path));
    }

    #[Test]
    public function it_lists_the_bundles_of_a_locale_without_the_root(): void
    {
        $data = new IcuData();

        self::assertSame(['sr_Latn_RS', 'sr_Latn', 'sr_RS', 'sr'], $data->candidates(new Locale('sr-Latn-RS')));
        self::assertSame(['nl_NL', 'nl'], $data->candidates(new Locale('nl-NL')));
        self::assertSame(['zz'], $data->candidates(new Locale('zz')));
    }

    #[Test]
    public function it_reads_a_value_from_one_bundle(): void
    {
        $data = new IcuData();

        self::assertSame('{0}、{1}', $data->stringFrom('ja', 'ICUDATA-lang', 'localeDisplayPattern', 'separator'));
        self::assertNull($data->stringFrom('en_GB', 'ICUDATA-lang', 'localeDisplayPattern', 'separator'));
        self::assertNull($data->stringFrom('zz', 'ICUDATA-lang', 'localeDisplayPattern', 'separator'));
    }

    #[Test]
    public function it_has_no_value_for_a_path_no_bundle_has(): void
    {
        $data = new IcuData();

        self::assertNull($data->string(new Locale('en-GB'), 'ICUDATA-lang', 'localeDisplayPattern', 'missing'));
        self::assertNull($data->string(new Locale('en-GB'), 'ICUDATA-lang', 'missing', 'separator'));
        self::assertNull($data->string(new Locale('en-GB'), 'ICUDATA-lang', 'localeDisplayPattern'));
    }

    /**
     * @return iterable<string, array{string, string, list<string>, string}>
     */
    public static function values(): iterable
    {
        yield 'from the language' => ['ja-JP', 'ICUDATA-lang', ['localeDisplayPattern', 'separator'], '{0}、{1}'];
        yield 'from the root' => ['zz', 'ICUDATA-lang', ['localeDisplayPattern', 'separator'], '{0}, {1}'];
        yield 'from the root past a script and region' => [
            'en-Latn-GB',
            'ICUDATA-lang',
            ['localeDisplayPattern', 'pattern'],
            '{0} ({1})',
        ];
        yield 'from another bundle' => ['da-DK', 'ICUDATA-unit', ['durationUnits', 'hms'], 'h.mm.ss'];
    }
}
