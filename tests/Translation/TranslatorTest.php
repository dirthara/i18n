<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Translation\Translator;
use Dirthara\I18n\Translation\PhpTranslationCache;
use Dirthara\I18n\Translation\PhpTranslationLoader;
use Dirthara\I18n\Translation\TranslationCatalogue;
use Dirthara\I18n\Tests\Fixtures\TemporaryDirectory;
use Dirthara\I18n\Translation\JsonTranslationLoader;
use Dirthara\I18n\Translation\CachedTranslationLoader;
use Dirthara\I18n\Translation\CombinedTranslationLoader;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Dirthara\I18n\Contract\Translator as TranslatorContract;

final class TranslatorTest extends TestCase
{
    #[Test]
    public function it_implements_the_translator_contract(): void
    {
        self::assertInstanceOf(TranslatorContract::class, $this->translator([]));
    }

    #[Test]
    public function it_takes_the_locale_of_its_catalogue(): void
    {
        $locale = new Locale('nl-NL');

        self::assertSame($locale, new Translator(new TranslationCatalogue($locale, []))->locale);
    }

    #[Test]
    public function it_translates_a_key(): void
    {
        $translator = $this->translator(['validation.required' => 'This field is required.', 'empty' => '']);

        self::assertSame('This field is required.', $translator->translate('validation.required'));
        self::assertSame('', $translator->translate('empty'));
    }

    #[Test]
    public function it_returns_the_key_it_has_no_translation_for(): void
    {
        $translator = $this->translator(['validation.required' => 'This field is required.']);

        self::assertSame('validation.missing', $translator->translate('validation.missing'));
        self::assertSame('Validation.required', $translator->translate('Validation.required'));
        self::assertSame('', $translator->translate(''));
    }

    #[Test]
    public function it_knows_which_keys_it_has_a_translation_for(): void
    {
        $translator = $this->translator(['validation.required' => 'This field is required.', 'empty' => '']);

        self::assertTrue($translator->has('validation.required'));
        self::assertTrue($translator->has('empty'));
        self::assertFalse($translator->has('validation'));
        self::assertFalse($translator->has('validation.missing'));
    }

    #[Test]
    public function it_replaces_placeholders_with_parameters(): void
    {
        $translator = $this->translator([
            'greeting' => 'Hello {name}, you have {count} messages worth {amount}.',
        ]);

        self::assertSame('Hello Ada, you have 3 messages worth 1.5.', $translator->translate('greeting', [
            'name' => 'Ada',
            'count' => 3,
            'amount' => 1.5,
        ]));
    }

    #[Test]
    public function it_replaces_every_occurrence_of_a_placeholder(): void
    {
        $translator = $this->translator(['echo' => '{word}, {word}, {word}!']);

        self::assertSame('Hey, Hey, Hey!', $translator->translate('echo', ['word' => 'Hey']));
    }

    #[Test]
    public function it_leaves_placeholders_without_a_parameter_as_they_are(): void
    {
        $translator = $this->translator(['between' => 'Between {min} and {max}.']);

        self::assertSame('Between 1 and {max}.', $translator->translate('between', ['min' => 1, 'unused' => 'x']));
        self::assertSame('Between {min} and {max}.', $translator->translate('between'));
    }

    #[Test]
    public function it_only_replaces_exact_placeholders(): void
    {
        $translator = $this->translator(['spacing' => '{ name } {Name} name {name']);

        self::assertSame('{ name } {Name} name {name', $translator->translate('spacing', ['name' => 'Ada']));
    }

    #[Test]
    public function it_does_not_replace_placeholders_inside_a_parameter(): void
    {
        $translator = $this->translator(['greeting' => 'Hello {name}, welcome to {site}.']);

        self::assertSame('Hello {site}, welcome to Dirthara.', $translator->translate('greeting', [
            'name' => '{site}',
            'site' => 'Dirthara',
        ]));
    }

    #[Test]
    public function it_keeps_apostrophes_and_other_braces_as_text(): void
    {
        $translator = $this->translator(['quote' => "It's {name}'s {turn}: {{name}}"]);

        self::assertSame("It's Ada's {turn}: {Ada}", $translator->translate('quote', ['name' => 'Ada']));
    }

    #[Test]
    public function it_replaces_placeholders_in_a_key_it_has_no_translation_for(): void
    {
        $translator = $this->translator([]);

        self::assertSame('Welcome, Ada', $translator->translate('Welcome, {name}', ['name' => 'Ada']));
    }

    #[Test]
    public function it_translates_from_a_cached_combination_of_php_and_json_translations(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('translations/en-GB/validation.php', "<?php\n\nreturn ['required' => 'Required.'];\n");
        $filesystem->write('translations/en-GB.json', '{"Log out": "Log out"}');
        $filesystem->write('packages/auth/en-GB.json', '{"failed": "These credentials do not match."}');
        $directory = new TemporaryDirectory();
        $loader = new CachedTranslationLoader(
            new CombinedTranslationLoader(
                new PhpTranslationLoader($filesystem, 'translations'),
                new JsonTranslationLoader($filesystem, 'translations'),
                new JsonTranslationLoader($filesystem, 'packages/auth', prefix: 'auth'),
            ),
            new PhpTranslationCache($directory->path),
            'application-translations',
        );

        try {
            $loader->load(new Locale('en-GB'));
            $filesystem->deleteDirectory('translations');
            $filesystem->deleteDirectory('packages');
            $translator = new Translator($loader->load(new Locale('en-GB')));
        } finally {
            $directory->remove();
        }

        self::assertSame('Required.', $translator->translate('validation.required'));
        self::assertSame('Log out', $translator->translate('Log out'));
        self::assertSame('These credentials do not match.', $translator->translate('auth.failed'));
    }

    /**
     * @param array<string, string> $messages
     */
    private function translator(array $messages): Translator
    {
        return new Translator(new TranslationCatalogue(new Locale('en-GB'), $messages));
    }
}
