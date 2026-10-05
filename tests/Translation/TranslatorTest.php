<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Translation\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Translation\PhpTranslationCache;
use Dirthara\I18n\Translation\PhpTranslationLoader;
use Dirthara\I18n\Translation\TranslationCatalogue;
use Dirthara\I18n\Tests\Fixtures\TemporaryDirectory;
use Dirthara\I18n\Translation\JsonTranslationLoader;
use Dirthara\I18n\Translation\CachedTranslationLoader;
use Dirthara\I18n\Exception\InvalidPluralCountException;
use Dirthara\I18n\Translation\CombinedTranslationLoader;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Dirthara\I18n\Contract\Translator as TranslatorContract;

use const INF;
use const NAN;

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
    #[DataProvider('englishCounts')]
    public function it_translates_the_plural_form_of_a_count(int|float $count, string $expected): void
    {
        $translator = $this->translator([
            'inbox.messages.one' => 'You have {count} message.',
            'inbox.messages.other' => 'You have {count} messages.',
        ]);

        self::assertSame($expected, $translator->translatePlural('inbox.messages', $count));
    }

    #[Test]
    public function it_translates_the_plural_forms_of_the_locale(): void
    {
        $translator = new Translator(new TranslationCatalogue(new Locale('pl-PL'), [
            'files.one' => '{count} plik',
            'files.few' => '{count} pliki',
            'files.many' => '{count} plików',
            'files.other' => '{count} pliku',
        ]));

        self::assertSame('1 plik', $translator->translatePlural('files', 1));
        self::assertSame('22 pliki', $translator->translatePlural('files', 22));
        self::assertSame('5 plików', $translator->translatePlural('files', 5));
        self::assertSame('1.5 pliku', $translator->translatePlural('files', 1.5));
    }

    #[Test]
    public function it_prefers_the_message_for_an_exact_count(): void
    {
        $translator = $this->translator([
            'inbox.messages.0' => 'You have no messages.',
            'inbox.messages.1' => 'You have exactly one message.',
            'inbox.messages.-1' => 'You owe a message.',
            'inbox.messages.1.5' => 'You have one and a half messages.',
            'inbox.messages.one' => 'You have {count} message.',
            'inbox.messages.other' => 'You have {count} messages.',
        ]);

        self::assertSame('You have no messages.', $translator->translatePlural('inbox.messages', 0));
        self::assertSame('You have no messages.', $translator->translatePlural('inbox.messages', 0.0));
        self::assertSame('You have exactly one message.', $translator->translatePlural('inbox.messages', 1));
        self::assertSame('You owe a message.', $translator->translatePlural('inbox.messages', -1));
        self::assertSame('You have one and a half messages.', $translator->translatePlural('inbox.messages', 1.5));
        self::assertSame('You have 2 messages.', $translator->translatePlural('inbox.messages', 2));
    }

    #[Test]
    public function it_falls_back_to_the_other_form(): void
    {
        $translator = new Translator(new TranslationCatalogue(new Locale('pl-PL'), [
            'files.one' => '{count} plik',
            'files.other' => '{count} pliku',
        ]));

        self::assertSame('5 pliku', $translator->translatePlural('files', 5));
    }

    #[Test]
    public function it_returns_the_key_it_has_no_plural_form_for(): void
    {
        $translator = $this->translator(['inbox.messages.one' => 'You have {count} message.', 'inbox' => 'Inbox']);

        self::assertSame('inbox.messages', $translator->translatePlural('inbox.messages', 2));
        self::assertSame('inbox', $translator->translatePlural('inbox', 2));
        self::assertSame('You have 1 messages', $translator->translatePlural('You have {count} messages', 1));
    }

    #[Test]
    public function it_knows_which_counts_it_has_a_plural_form_for(): void
    {
        $translator = new Translator(new TranslationCatalogue(new Locale('pl-PL'), [
            'files.0' => 'Brak plików',
            'files.one' => '{count} plik',
            'files.few' => '{count} pliki',
            'cart' => 'Koszyk',
            'cart.items.few' => '{count} produkty',
        ]));

        self::assertTrue($translator->hasPlural('files', 0));
        self::assertTrue($translator->hasPlural('files', 1));
        self::assertTrue($translator->hasPlural('files', 2));
        self::assertFalse($translator->hasPlural('files', 5));
        self::assertFalse($translator->hasPlural('cart', 2));
        self::assertTrue($translator->hasPlural('cart.items', 3));
        self::assertFalse($translator->hasPlural('cart.items', 1));
        self::assertFalse($translator->hasPlural('missing', 1));
    }

    #[Test]
    public function it_has_a_plural_form_for_every_count_when_there_is_an_other_form(): void
    {
        $translator = $this->translator(['inbox.messages.other' => '{count} messages']);

        self::assertTrue($translator->hasPlural('inbox.messages', 1));
        self::assertTrue($translator->hasPlural('inbox.messages', 0));
        self::assertTrue($translator->hasPlural('inbox.messages', 1.5));
    }

    #[Test]
    public function it_keeps_has_for_exact_keys(): void
    {
        $translator = $this->translator([
            'inbox.messages.one' => '{count} message',
            'inbox.messages.other' => '{count} messages',
        ]);

        self::assertFalse($translator->has('inbox.messages'));
        self::assertTrue($translator->has('inbox.messages.one'));
        self::assertTrue($translator->hasPlural('inbox.messages', 2));
    }

    #[Test]
    #[DataProvider('englishCounts')]
    public function it_agrees_with_translate_plural_on_whether_a_form_exists(int|float $count, string $expected): void
    {
        $translator = $this->translator([
            'inbox.messages.one' => 'You have {count} message.',
            'inbox.messages.other' => 'You have {count} messages.',
        ]);
        $onlyOne = $this->translator(['inbox.messages.one' => 'You have {count} message.']);

        self::assertTrue($translator->hasPlural('inbox.messages', $count));
        self::assertSame($expected, $translator->translatePlural('inbox.messages', $count));
        self::assertSame(
            $onlyOne->hasPlural('inbox.messages', $count),
            $onlyOne->translatePlural('inbox.messages', $count) !== 'inbox.messages',
        );
    }

    #[Test]
    public function it_selects_the_form_by_the_count_not_by_a_count_parameter(): void
    {
        $translator = $this->translator(['items.one' => 'One item ({count})', 'items.other' => '{count} items']);

        self::assertSame('One item (one)', $translator->translatePlural('items', 1, ['count' => 'one']));
        self::assertSame('many items', $translator->translatePlural('items', 2, ['count' => 'many']));
    }

    #[Test]
    #[DataProvider('nonFiniteCounts')]
    public function it_rejects_a_plural_count_that_is_not_finite(float $count): void
    {
        $translator = $this->translator([
            'inbox.messages.NAN' => 'Not a number',
            'inbox.messages.INF' => 'Infinite',
            'inbox.messages.-INF' => 'Negative infinite',
            'inbox.messages.other' => '{count} messages',
        ]);

        foreach ([
            static fn(): string => $translator->translatePlural('inbox.messages', $count),
            static fn(): bool => $translator->hasPlural('inbox.messages', $count),
        ] as $call) {
            try {
                $call();
                self::fail('Expected an InvalidPluralCountException.');
            } catch (InvalidPluralCountException $exception) {
                self::assertSame('en-GB', $exception->context['locale']);
            }
        }
    }

    #[Test]
    public function it_fills_in_the_count_unless_a_count_parameter_is_given(): void
    {
        $translator = $this->translator(['visitors.other' => '{count} visitors on {site}']);

        self::assertSame('1500 visitors on Dirthara', $translator->translatePlural('visitors', 1500, [
            'site' => 'Dirthara',
        ]));
        self::assertSame('1,500 visitors on Dirthara', $translator->translatePlural('visitors', 1500, [
            'count' => '1,500',
            'site' => 'Dirthara',
        ]));
    }

    #[Test]
    public function it_translates_plural_forms_from_php_and_json_translations(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('translations/en-GB/inbox.php', <<<'PHP'
            <?php

            return [
                'messages' => [
                    '0' => 'You have no messages.',
                    'one' => 'You have {count} message.',
                    'other' => 'You have {count} messages.',
                ],
            ];
            PHP);
        $filesystem->write(
            'translations/en-GB.json',
            '{"cart.items.one": "{count} item in your cart", "cart.items.other": "{count} items in your cart"}',
        );
        $translator = new Translator(new CombinedTranslationLoader(
            new PhpTranslationLoader($filesystem, 'translations', prefix: 'app'),
            new JsonTranslationLoader($filesystem, 'translations'),
        )->load(new Locale('en-GB')));

        self::assertSame('You have no messages.', $translator->translatePlural('app.inbox.messages', 0));
        self::assertSame('You have 1 message.', $translator->translatePlural('app.inbox.messages', 1));
        self::assertSame('You have 7 messages.', $translator->translatePlural('app.inbox.messages', 7));
        self::assertSame('1 item in your cart', $translator->translatePlural('cart.items', 1));
        self::assertSame('3 items in your cart', $translator->translatePlural('cart.items', 3));
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
     * @return iterable<string, array{int|float, string}>
     */
    public static function englishCounts(): iterable
    {
        yield 'zero' => [0, 'You have 0 messages.'];
        yield 'one' => [1, 'You have 1 message.'];
        yield 'two' => [2, 'You have 2 messages.'];
        yield 'fraction' => [1.5, 'You have 1.5 messages.'];
        yield 'negative one' => [-1, 'You have -1 message.'];
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function nonFiniteCounts(): iterable
    {
        yield 'not a number' => [NAN];
        yield 'infinity' => [INF];
        yield 'negative infinity' => [-INF];
    }

    /**
     * @param array<string, string> $messages
     */
    private function translator(array $messages): Translator
    {
        return new Translator(new TranslationCatalogue(new Locale('en-GB'), $messages));
    }
}
