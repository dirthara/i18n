<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Locale;

use Closure;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Formatter\Locale\LocaleFormatter;
use Dirthara\I18n\Contract\LocaleFormatter as LocaleFormatterContract;

use function sprintf;

final class LocaleFormatterTest extends TestCase
{
    #[Test]
    public function it_implements_the_locale_formatter_contract(): void
    {
        self::assertInstanceOf(LocaleFormatterContract::class, new LocaleFormatter(new Locale('en-GB')));
    }

    #[Test]
    public function it_exposes_its_locale(): void
    {
        $locale = new Locale('nl-NL');

        self::assertSame($locale, new LocaleFormatter($locale)->locale);
    }

    #[Test]
    #[DataProvider('names')]
    public function it_names_a_locale_in_its_own_locale(string $in, string $locale, string $expected): void
    {
        self::assertSame($expected, new LocaleFormatter(new Locale($in))->format(new Locale($locale)));
    }

    #[Test]
    public function it_names_the_parts_of_a_locale(): void
    {
        $formatter = new LocaleFormatter(new Locale('nl-NL'));
        $locale = new Locale('zh-Hant-TW');

        self::assertSame('Chinees', $formatter->language($locale));
        self::assertSame('Taiwan', $formatter->region($locale));
        self::assertSame('traditioneel Chinees', $formatter->script($locale));
        self::assertSame([], $formatter->variants($locale));
    }

    #[Test]
    public function it_has_no_name_for_a_part_the_locale_does_not_have(): void
    {
        $formatter = new LocaleFormatter(new Locale('en-GB'));
        $locale = new Locale('en');

        self::assertSame('English', $formatter->language($locale));
        self::assertNull($formatter->region($locale));
        self::assertNull($formatter->script($locale));
        self::assertSame([], $formatter->variants($locale));
    }

    #[Test]
    public function it_names_every_variant(): void
    {
        $formatter = new LocaleFormatter(new Locale('en-GB'));

        self::assertSame(
            ['Traditional German orthography', 'German orthography of 1996'],
            $formatter->variants(new Locale('de-CH-1901-1996')),
        );
        self::assertSame(['Resian', 'San Giorgio/Bila dialect'], $formatter->variants(new Locale('sl-rozaj-biske')));
    }

    #[Test]
    public function it_falls_back_to_the_code_of_a_part_it_has_no_name_for(): void
    {
        $formatter = new LocaleFormatter(new Locale('en-GB'));
        $locale = new Locale('zz-Abcd-AA');

        self::assertSame('zz', $formatter->language($locale));
        self::assertSame('AA', $formatter->region($locale));
        self::assertSame('Abcd', $formatter->script($locale));
    }

    #[Test]
    #[DataProvider('overlongCalls')]
    public function it_reports_a_locale_intl_cannot_name(Closure $name, string $style, string $function): void
    {
        $code = 'en';

        for ($variant = 0; $variant < 18; $variant++) {
            $code .= sprintf('-v%07d', $variant);
        }

        $formatter = new LocaleFormatter(new Locale('en-GB'));

        try {
            $name($formatter, new Locale($code));
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                sprintf(
                    'The %s locale formatter for locale "en-GB" failed to format a value: Locale::%s(): name too '
                    . 'long: U_ILLEGAL_ARGUMENT_ERROR.',
                    $style,
                    $function,
                ),
                $exception->getMessage(),
            );
            self::assertSame('locale', $exception->context['formatter']);
            self::assertSame(1, $exception->context['intlCode']);
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function names(): iterable
    {
        yield 'language' => ['en-GB', 'en', 'English'];
        yield 'language and region' => ['en-GB', 'nl-BE', 'Dutch (Belgium)'];
        yield 'script and region' => ['en-GB', 'zh-Hant-TW', 'Chinese (Traditional, Taiwan)'];
        yield 'in Dutch' => ['nl-NL', 'zh-Hant-TW', 'Chinees (traditioneel, Taiwan)'];
        yield 'in Japanese' => ['ja-JP', 'zh-Hant-TW', '中国語 (繁体字、台湾)'];
        yield 'one variant' => ['en-GB', 'de-CH-1901', 'German (Switzerland, Traditional German orthography)'];
        yield 'two variants' => [
            'en-GB',
            'de-CH-1901-1996',
            'German (Switzerland, Traditional German orthography, German orthography of 1996)',
        ];
        yield 'two variants in Japanese' => [
            'ja-JP',
            'de-CH-1901-1996',
            'ドイツ語 (スイス、ドイツ語旧正書法、ドイツ語正書法(1996))',
        ];
        yield 'two variants in Chinese' => [
            'zh-Hant-TW',
            'de-CH-1901-1996',
            '德文（瑞士，傳統德語拼字學，1996 年的德語拼字學）',
        ];
        yield 'two variants with a script and region' => [
            'sr-Latn-RS',
            'de-CH-1901-1996',
            'nemački (Švajcarska, Tradicionalna nemačka ortografija, Nemačka ortografija iz 1996)',
        ];
        yield 'two variants without data' => [
            'zz',
            'de-CH-1901-1996',
            'German (Switzerland, Traditional German orthography, German orthography of 1996)',
        ];
        yield 'two variants without a region' => [
            'en-GB',
            'sl-rozaj-biske',
            'Slovenian (Resian, San Giorgio/Bila dialect)',
        ];
        yield 'unassigned' => ['en-GB', 'zz-AA', 'zz (AA)'];
    }

    /**
     * @return iterable<string, array{Closure(LocaleFormatter, Locale): string, string, string}>
     */
    public static function overlongCalls(): iterable
    {
        yield 'name' => [
            static fn(LocaleFormatter $formatter, Locale $locale): string => $formatter->format($locale),
            'name',
            'getDisplayName',
        ];
        yield 'language' => [
            static fn(LocaleFormatter $formatter, Locale $locale): string => $formatter->language($locale),
            'language',
            'getDisplayLanguage',
        ];
    }
}
