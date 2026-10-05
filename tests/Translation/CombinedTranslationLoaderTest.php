<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Contract\TranslationLoader;
use Dirthara\I18n\Translation\PhpTranslationCache;
use Dirthara\I18n\Tests\Fixtures\TemporaryDirectory;
use Dirthara\I18n\Translation\CachedTranslationLoader;
use Dirthara\I18n\Exception\TranslationLoaderException;
use Dirthara\I18n\Translation\CombinedTranslationLoader;
use Dirthara\I18n\Tests\Fixtures\CountingTranslationLoader;

final class CombinedTranslationLoaderTest extends TestCase
{
    #[Test]
    public function it_is_a_translation_loader(): void
    {
        self::assertInstanceOf(TranslationLoader::class, new CombinedTranslationLoader());
    }

    #[Test]
    public function it_combines_the_catalogues_of_its_loaders_for_the_requested_locale(): void
    {
        $loader = new CombinedTranslationLoader(
            new CountingTranslationLoader([
                'en-GB' => ['validation.required' => 'Required.'],
                'nl-NL' => ['validation.required' => 'Verplicht.'],
            ]),
            new CountingTranslationLoader(['en-GB' => ['Log out' => 'Log out']]),
        );

        $catalogue = $loader->load(new Locale('en-GB'));

        self::assertSame('en-GB', $catalogue->locale->code);
        self::assertSame(['validation.required' => 'Required.', 'Log out' => 'Log out'], $catalogue->messages);
        self::assertSame(['validation.required' => 'Verplicht.'], $loader->load(new Locale('nl-NL'))->messages);
    }

    #[Test]
    public function it_loads_nothing_without_loaders(): void
    {
        $catalogue = new CombinedTranslationLoader()->load(new Locale('en-GB'));

        self::assertSame('en-GB', $catalogue->locale->code);
        self::assertSame([], $catalogue->messages);
    }

    #[Test]
    public function it_loads_each_loader_once_per_load(): void
    {
        $first = new CountingTranslationLoader();
        $second = new CountingTranslationLoader();
        $loader = new CombinedTranslationLoader($first, $second);

        $loader->load(new Locale('en-GB'));
        $loader->load(new Locale('nl-NL'));

        self::assertSame(2, $first->loads);
        self::assertSame(2, $second->loads);
    }

    #[Test]
    public function it_combines_a_combined_loader(): void
    {
        $loader = new CombinedTranslationLoader(
            new CombinedTranslationLoader(
                new CountingTranslationLoader(['en-GB' => ['a' => 'A']]),
                new CountingTranslationLoader(['en-GB' => ['b' => 'B']]),
            ),
            new CountingTranslationLoader(['en-GB' => ['c' => 'C']]),
        );

        self::assertSame(['a' => 'A', 'b' => 'B', 'c' => 'C'], $loader->load(new Locale('en-GB'))->messages);
    }

    #[Test]
    public function it_rejects_a_key_two_loaders_produce(): void
    {
        $loader = new CombinedTranslationLoader(
            new CountingTranslationLoader(['en-GB' => ['welcome' => 'First secret']]),
            new CountingTranslationLoader(['en-GB' => ['goodbye' => 'Goodbye']]),
            new CountingTranslationLoader(['en-GB' => ['welcome' => 'Second secret']]),
        );

        $exception = $this->failure($loader);

        self::assertSame(
            'The translation key "welcome" for locale "en-GB" is defined by both loader 0 and loader 2.',
            $exception->getMessage(),
        );
        self::assertSame(['locale' => 'en-GB', 'key' => 'welcome', 'loaders' => [0, 2]], $exception->context);
    }

    #[Test]
    public function it_names_loaders_passed_by_name_in_a_conflict(): void
    {
        $loader = new CombinedTranslationLoader(...[
            'application' => new CountingTranslationLoader(['en-GB' => ['welcome' => 'Welcome']]),
            'package' => new CountingTranslationLoader(['en-GB' => ['welcome' => 'Welcome']]),
        ]);

        $exception = $this->failure($loader);

        self::assertSame(
            'The translation key "welcome" for locale "en-GB" is defined by both loader application and loader package.',
            $exception->getMessage(),
        );
        self::assertSame(['application', 'package'], $exception->context['loaders']);
    }

    #[Test]
    public function it_rejects_a_catalogue_for_another_locale(): void
    {
        $loader = new CombinedTranslationLoader(
            new CountingTranslationLoader(),
            new CountingTranslationLoader(answerWith: new Locale('nl-NL')),
        );

        self::assertSame(
            ['locale' => 'en-GB', 'loadedLocale' => 'nl-NL', 'loader' => 1],
            $this->failure($loader)->context,
        );
    }

    #[Test]
    public function it_is_cached_as_one_entry_without_loading_or_combining_again(): void
    {
        $directory = new TemporaryDirectory();
        $php = new CountingTranslationLoader(['en-GB' => ['validation.required' => 'Required.']]);
        $json = new CountingTranslationLoader(['en-GB' => ['Log out' => 'Log out']]);
        $loader = new CachedTranslationLoader(
            new CombinedTranslationLoader($php, $json),
            new PhpTranslationCache($directory->path),
            'application-translations',
        );

        try {
            $first = $loader->load(new Locale('en-GB'));
            $second = $loader->load(new Locale('en-GB'));
            $files = $directory->files();
        } finally {
            $directory->remove();
        }

        self::assertEquals($first->messages, $second->messages);
        self::assertEquals(['validation.required' => 'Required.', 'Log out' => 'Log out'], $second->messages);
        self::assertSame(1, $php->loads);
        self::assertSame(1, $json->loads);
        self::assertCount(1, $files);
    }

    #[Test]
    public function it_does_not_cache_a_conflict(): void
    {
        $directory = new TemporaryDirectory();
        $loader = new CachedTranslationLoader(
            new CombinedTranslationLoader(
                new CountingTranslationLoader(['en-GB' => ['welcome' => 'Welcome']]),
                new CountingTranslationLoader(['en-GB' => ['welcome' => 'Welcome']]),
            ),
            new PhpTranslationCache($directory->path),
            'application-translations',
        );

        try {
            $this->failure($loader);
            $files = $directory->files();
        } finally {
            $directory->remove();
        }

        self::assertSame([], $files);
    }

    private function failure(TranslationLoader $loader): TranslationLoaderException
    {
        try {
            $loader->load(new Locale('en-GB'));
        } catch (TranslationLoaderException $exception) {
            return $exception;
        }

        self::fail('Expected a TranslationLoaderException.');
    }
}
