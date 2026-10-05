<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\List;

use stdClass;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use Dirthara\I18n\Enum\ListType;
use Dirthara\I18n\Enum\ListWidth;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Formatter\List\ListFormatter;
use Dirthara\I18n\Exception\InvalidListItemException;
use Dirthara\I18n\Contract\ListFormatter as ListFormatterContract;

use function sprintf;

final class ListFormatterTest extends TestCase
{
    #[Test]
    public function it_implements_the_list_formatter_contract(): void
    {
        self::assertInstanceOf(ListFormatterContract::class, new ListFormatter(new Locale('en-GB')));
    }

    #[Test]
    public function it_exposes_its_locale(): void
    {
        $locale = new Locale('nl-NL');

        self::assertSame($locale, new ListFormatter($locale)->locale);
    }

    #[Test]
    #[DataProvider('lists')]
    public function it_formats_a_list_for_its_locale(
        string $locale,
        ListType $type,
        ListWidth $width,
        string $expected,
    ): void {
        self::assertSame($expected, new ListFormatter(new Locale($locale))->format(['a', 'b', 'c'], $type, $width));
    }

    #[Test]
    public function it_formats_a_wide_and_list_by_default(): void
    {
        self::assertSame(
            'apples, pears and plums',
            new ListFormatter(new Locale('en-GB'))->format([
                'apples',
                'pears',
                'plums',
            ]),
        );
    }

    #[Test]
    public function it_formats_short_lists(): void
    {
        $formatter = new ListFormatter(new Locale('en-GB'));

        self::assertSame('', $formatter->format([]));
        self::assertSame('apples', $formatter->format(['apples']));
        self::assertSame('apples and pears', $formatter->format(['apples', 'pears']));
        self::assertSame('apples or pears', $formatter->format(['apples', 'pears'], ListType::Or));
    }

    #[Test]
    public function it_formats_the_items_in_their_order_whatever_their_keys(): void
    {
        self::assertSame('b, a and c', new ListFormatter(new Locale('en-GB'))->format([
            2 => 'b',
            0 => 'a',
            'x' => 'c',
        ]));
    }

    #[Test]
    public function it_formats_with_several_types_and_widths_side_by_side(): void
    {
        $formatter = new ListFormatter(new Locale('nl-NL'));

        self::assertSame('a, b en c', $formatter->format(['a', 'b', 'c']));
        self::assertSame('a, b & c', $formatter->format(['a', 'b', 'c'], ListType::And, ListWidth::Short));
        self::assertSame('a, b of c', $formatter->format(['a', 'b', 'c'], ListType::Or));
        self::assertSame('a, b en c', $formatter->format(['a', 'b', 'c']));
    }

    #[Test]
    #[DataProvider('nonStrings')]
    public function it_rejects_an_item_that_is_not_a_string(mixed $item, string $type): void
    {
        try {
            new ListFormatter(new Locale('en-GB'))->format(['apples', 'fruit' => $item]);
            self::fail('Expected an InvalidListItemException.');
        } catch (InvalidListItemException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                sprintf(
                    'The list formatter for locale "en-GB" cannot format item fruit: it is %s, not a string.',
                    $type,
                ),
                $exception->getMessage(),
            );
            self::assertSame(
                ['formatter' => 'list', 'locale' => 'en-GB', 'position' => 'fruit', 'type' => $type],
                $exception->context,
            );
        }
    }

    #[Test]
    public function it_reports_an_item_intl_cannot_format(): void
    {
        try {
            new ListFormatter(new Locale('en-GB'))->format(['apples', "\xFF\xFE"], ListType::Or, ListWidth::Narrow);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame('list', $exception->context['formatter']);
            self::assertSame('en-GB', $exception->context['locale']);
            self::assertSame('or-narrow', $exception->context['style']);
            self::assertSame(10, $exception->context['intlCode']);
            self::assertStringContainsString('U_INVALID_CHAR_FOUND', $exception->getMessage());
        }
    }

    #[Test]
    public function it_reports_a_locale_intl_cannot_create_a_formatter_for(): void
    {
        $code = 'en';

        for ($variant = 0; $variant < 18; $variant++) {
            $code .= sprintf('-v%07d', $variant);
        }

        $locale = new Locale($code);

        try {
            new ListFormatter($locale)->format(['a', 'b']);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame(
                'Unable to create the and-wide list formatter for locale "' . $locale->code . '".',
                $exception->getMessage(),
            );
            self::assertSame(
                ['formatter' => 'list', 'locale' => $locale->code, 'style' => 'and-wide'],
                $exception->context,
            );
        }
    }

    /**
     * @return iterable<string, array{string, ListType, ListWidth, string}>
     */
    public static function lists(): iterable
    {
        yield 'British and' => ['en-GB', ListType::And, ListWidth::Wide, 'a, b and c'];
        yield 'British narrow and' => ['en-GB', ListType::And, ListWidth::Narrow, 'a, b, c'];
        yield 'British or' => ['en-GB', ListType::Or, ListWidth::Wide, 'a, b or c'];
        yield 'British units' => ['en-GB', ListType::Units, ListWidth::Wide, 'a, b, c'];
        yield 'British narrow units' => ['en-GB', ListType::Units, ListWidth::Narrow, 'a b c'];
        yield 'Dutch and' => ['nl-NL', ListType::And, ListWidth::Wide, 'a, b en c'];
        yield 'Dutch short and' => ['nl-NL', ListType::And, ListWidth::Short, 'a, b & c'];
        yield 'German or' => ['de-DE', ListType::Or, ListWidth::Wide, 'a, b oder c'];
        yield 'French and' => ['fr-FR', ListType::And, ListWidth::Wide, 'a, b et c'];
        yield 'Japanese and' => ['ja-JP', ListType::And, ListWidth::Wide, "a\u{3001}b\u{3001}c"];
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function nonStrings(): iterable
    {
        yield 'integer' => [1, 'int'];
        yield 'null' => [null, 'null'];
        yield 'array' => [['nested'], 'array'];
        yield 'object' => [new stdClass(), 'stdClass'];
    }
}
