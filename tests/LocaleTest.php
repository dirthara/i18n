<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests;

use ValueError;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\InvalidLocaleException;

use function str_repeat;

final class LocaleTest extends TestCase
{
    /**
     * @param list<string> $variants
     */
    #[Test]
    #[DataProvider('validLocales')]
    public function it_parses_a_valid_locale(
        string $input,
        string $code,
        string $language,
        ?string $script,
        ?string $region,
        array $variants,
    ): void {
        $locale = new Locale($input);

        self::assertSame($code, $locale->code);
        self::assertSame($language, $locale->language);
        self::assertSame($script, $locale->script);
        self::assertSame($region, $locale->region);
        self::assertSame($variants, $locale->variants);
    }

    #[Test]
    #[DataProvider('validInputs')]
    public function it_accepts_its_own_code(string $input): void
    {
        $locale = new Locale($input);

        self::assertSame($locale->code, new Locale($locale->code)->code);
    }

    #[Test]
    #[DataProvider('invalidLocales')]
    public function it_rejects_an_invalid_locale(string $input): void
    {
        $this->expectException(InvalidLocaleException::class);

        new Locale($input);
    }

    #[Test]
    public function it_reports_the_rejected_locale(): void
    {
        try {
            new Locale('en US');
            self::fail('Expected an InvalidLocaleException.');
        } catch (InvalidLocaleException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame('en US is not a valid locale.', $exception->getMessage());
            self::assertSame(['locale' => 'en US'], $exception->context);
            self::assertNull($exception->getPrevious());
        }
    }

    #[Test]
    public function it_escapes_control_characters_in_the_message(): void
    {
        try {
            new Locale("en\nUS");
            self::fail('Expected an InvalidLocaleException.');
        } catch (InvalidLocaleException $exception) {
            self::assertSame('en\\nUS is not a valid locale.', $exception->getMessage());
            self::assertSame(['locale' => "en\nUS"], $exception->context);
        }
    }

    #[Test]
    public function it_keeps_an_error_as_the_previous_throwable(): void
    {
        $previous = new ValueError('Not a locale.');

        self::assertSame($previous, InvalidLocaleException::forLocale('en', $previous)->getPrevious());
    }

    #[Test]
    #[DataProvider('rightToLeftLocales')]
    public function it_knows_a_right_to_left_locale(string $input): void
    {
        self::assertTrue(new Locale($input)->isRightToLeft());
    }

    #[Test]
    #[DataProvider('leftToRightLocales')]
    public function it_knows_a_left_to_right_locale(string $input): void
    {
        self::assertFalse(new Locale($input)->isRightToLeft());
    }

    #[Test]
    public function it_equals_a_locale_with_the_same_code(): void
    {
        $locale = new Locale('en-US');

        self::assertTrue($locale->equals($locale));
        self::assertTrue($locale->equals(new Locale('en_US')));
        self::assertTrue($locale->equals(new Locale('EN-us')));
    }

    #[Test]
    public function it_does_not_equal_a_locale_with_another_code(): void
    {
        $locale = new Locale('en-US');

        self::assertFalse($locale->equals(new Locale('en')));
        self::assertFalse($locale->equals(new Locale('en-GB')));
        self::assertFalse($locale->equals(new Locale('nl-US')));
    }

    /**
     * @return iterable<string, array{string, string, string, ?string, ?string, list<string>}>
     */
    public static function validLocales(): iterable
    {
        yield 'language' => ['en', 'en', 'en', null, null, []];
        yield 'language and region' => ['en-US', 'en-US', 'en', null, 'US', []];
        yield 'underscore separator' => ['en_US', 'en-US', 'en', null, 'US', []];
        yield 'lowercase region' => ['en-us', 'en-US', 'en', null, 'US', []];
        yield 'uppercase language' => ['EN-us', 'en-US', 'en', null, 'US', []];
        yield 'three letter language' => ['fil-PH', 'fil-PH', 'fil', null, 'PH', []];
        yield 'language and script' => ['sr-Latn', 'sr-Latn', 'sr', 'Latn', null, []];
        yield 'language, script, and region' => ['zh-Hant-TW', 'zh-Hant-TW', 'zh', 'Hant', 'TW', []];
        yield 'script with underscores' => ['sr_Latn_RS', 'sr-Latn-RS', 'sr', 'Latn', 'RS', []];
        yield 'numeric region' => ['es-419', 'es-419', 'es', null, '419', []];
        yield 'variant' => ['de-DE-1996', 'de-DE-1996', 'de', null, 'DE', ['1996']];
        yield 'two variants' => ['de-CH-1901-1996', 'de-CH-1901-1996', 'de', null, 'CH', ['1901', '1996']];
        yield 'five letter language' => ['POSIX', 'posix', 'posix', null, null, []];
        yield 'eight letter language' => ['abcdefgh-Latn-US', 'abcdefgh-Latn-US', 'abcdefgh', 'Latn', 'US', []];
        yield 'undetermined language' => ['und', 'und', 'und', null, null, []];
        yield 'lowercase script' => ['az-cyrl-az', 'az-Cyrl-AZ', 'az', 'Cyrl', 'AZ', []];
        yield 'five letter variant' => ['en-latin', 'en-latin', 'en', null, null, ['latin']];
        yield 'variant that starts like a script' => ['en-Latn1', 'en-latn1', 'en', null, null, ['latn1']];
        yield 'variants without region' => ['sl-rozaj-biske', 'sl-rozaj-biske', 'sl', null, null, ['rozaj', 'biske']];
        yield 'uppercase variant' => ['de_CH_1901', 'de-CH-1901', 'de', null, 'CH', ['1901']];
        yield 'unassigned language' => ['zz', 'zz', 'zz', null, null, []];
        yield 'unassigned script' => ['en-Abcd', 'en-Abcd', 'en', 'Abcd', null, []];
        yield 'unassigned region' => ['en-AA', 'en-AA', 'en', null, 'AA', []];
        yield 'unassigned numeric region' => ['en-999', 'en-999', 'en', null, '999', []];
        yield 'all subtags' => ['SR-latn-rs-EKAVSK', 'sr-Latn-RS-ekavsk', 'sr', 'Latn', 'RS', ['ekavsk']];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validInputs(): iterable
    {
        foreach (self::validLocales() as $name => [$input]) {
            yield $name => [$input];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLocales(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['en US'];
        yield 'newline' => ["en\nUS"];
        yield 'null byte' => ["en\0US"];
        yield 'leading separator' => ['-en'];
        yield 'trailing separator' => ['en_'];
        yield 'double separator' => ['en--US'];
        yield 'keyword' => ['en@calendar=gregorian'];
        yield 'dot' => ['en_US.UTF-8'];
        yield 'too long language' => ['toolonglanguage'];
        yield 'too long' => [str_repeat('a', times: 200)];
        yield 'private use only' => ['x-private'];
        yield 'private use' => ['en-US-x-foo'];
        yield 'extension' => ['en-US-u-ca-gregory'];
        yield 'root' => ['root'];
        yield 'grandfathered tag' => ['i-klingon'];
        yield 'deprecated tag' => ['zh-min-nan'];
        yield 'trailing newline' => ["en\n"];
        yield 'numeric language' => ['123'];
        yield 'one letter language' => ['a'];
        yield 'POSIX C locale' => ['C'];
        yield 'duplicate variant' => ['de-1996-1996'];
        yield 'duplicate variant in another case' => ['sl-rozaj-ROZAJ'];
        yield 'extended language' => ['zh-yue-HK'];
        yield 'short variant' => ['en-US-foo'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rightToLeftLocales(): iterable
    {
        yield 'Arabic' => ['ar'];
        yield 'Hebrew' => ['he-IL'];
        yield 'Persian' => ['fa-IR'];
        yield 'Urdu' => ['ur'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function leftToRightLocales(): iterable
    {
        yield 'English' => ['en-US'];
        yield 'Dutch' => ['nl'];
        yield 'Chinese' => ['zh-Hant-TW'];
        yield 'Japanese' => ['ja'];
    }
}
