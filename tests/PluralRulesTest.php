<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests;

use Dirthara\I18n\Locale;
use Dirthara\I18n\PluralRules;
use PHPUnit\Framework\TestCase;
use Dirthara\I18n\Enum\PluralCategory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

final class PluralRulesTest extends TestCase
{
    #[Test]
    public function it_exposes_its_locale(): void
    {
        $locale = new Locale('en-GB');

        self::assertSame($locale, new PluralRules($locale)->locale);
    }

    #[Test]
    #[DataProvider('categories')]
    public function it_gives_the_cldr_plural_category_of_a_count(
        string $locale,
        int|float $count,
        PluralCategory $category,
    ): void {
        self::assertSame($category, new PluralRules(new Locale($locale))->category($count));
    }

    #[Test]
    public function it_gives_the_same_categories_from_several_instances(): void
    {
        $first = new PluralRules(new Locale('pl-PL'));
        $second = new PluralRules(new Locale('pl-PL'));
        $english = new PluralRules(new Locale('en-GB'));

        self::assertSame(PluralCategory::Few, $first->category(2));
        self::assertSame(PluralCategory::Few, $second->category(2));
        self::assertSame(PluralCategory::Other, $english->category(2));
        self::assertSame(PluralCategory::Many, $first->category(5));
    }

    /**
     * @return iterable<string, array{string, int|float, PluralCategory}>
     */
    public static function categories(): iterable
    {
        yield 'English one' => ['en-GB', 1, PluralCategory::One];
        yield 'English zero is other' => ['en-GB', 0, PluralCategory::Other];
        yield 'English two' => ['en-GB', 2, PluralCategory::Other];
        yield 'English fraction' => ['en-GB', 1.5, PluralCategory::Other];
        yield 'English negative one' => ['en-GB', -1, PluralCategory::One];
        yield 'French zero is one' => ['fr-FR', 0, PluralCategory::One];
        yield 'French fraction is one' => ['fr-FR', 1.5, PluralCategory::One];
        yield 'French two' => ['fr-FR', 2, PluralCategory::Other];
        yield 'Polish one' => ['pl-PL', 1, PluralCategory::One];
        yield 'Polish few' => ['pl-PL', 22, PluralCategory::Few];
        yield 'Polish many' => ['pl-PL', 5, PluralCategory::Many];
        yield 'Polish fraction' => ['pl-PL', 1.5, PluralCategory::Other];
        yield 'Russian twenty-one is one' => ['ru', 21, PluralCategory::One];
        yield 'Arabic zero' => ['ar', 0, PluralCategory::Zero];
        yield 'Arabic one' => ['ar', 1, PluralCategory::One];
        yield 'Arabic two' => ['ar', 2, PluralCategory::Two];
        yield 'Arabic few' => ['ar', 3, PluralCategory::Few];
        yield 'Arabic many' => ['ar', 11, PluralCategory::Many];
        yield 'Arabic other' => ['ar', 100, PluralCategory::Other];
        yield 'Japanese has only other' => ['ja', 1, PluralCategory::Other];
        yield 'Latvian zero' => ['lv', 10, PluralCategory::Zero];
        yield 'unassigned language' => ['zz', 1, PluralCategory::Other];
    }
}
