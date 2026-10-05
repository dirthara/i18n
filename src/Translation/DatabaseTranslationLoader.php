<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Dirthara\I18n\Locale;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\I18n\Contract\TranslationLoader;
use Dirthara\Database\Exception\DatabaseException;
use Dirthara\I18n\Exception\TranslationLoaderException;
use Dirthara\I18n\Exception\InvalidTranslationPrefixException;

use function is_string;
use function array_key_exists;

final readonly class DatabaseTranslationLoader implements TranslationLoader
{
    private TranslationPrefix $prefix;

    private TranslationKeyRule $keys;

    /**
     * @throws InvalidTranslationPrefixException
     */
    public function __construct(
        private ConnectedDatabase $database,
        private string $table = 'translations',
        ?string $prefix = null,
    ) {
        $this->prefix = new TranslationPrefix($prefix);
        $this->keys = new TranslationKeyRule();
    }

    /**
     * @throws TranslationLoaderException
     */
    public function load(Locale $locale): TranslationCatalogue
    {
        try {
            $rows = $this->database
                ->table($this->table)
                ->select('locale', 'key', 'translation')
                ->where('locale', '=', $locale->code)
                ->get();
        } catch (DatabaseException $exception) {
            throw TranslationLoaderException::queryFailed($this->table, $locale, previous: $exception);
        }

        $messages = [];

        foreach ($rows as $row) {
            // A case-insensitive or padding collation matches more than the exact code, so the match is repeated here.
            if (($row['locale'] ?? null) !== $locale->code) {
                continue;
            }

            // @mago-expect analysis:mixed-assignment
            $key = $row['key'] ?? null;
            // @mago-expect analysis:mixed-assignment
            $translation = $row['translation'] ?? null;

            if (!is_string($key)) {
                throw TranslationLoaderException::malformedRow($this->table, $locale, column: 'key');
            }

            if (!is_string($translation)) {
                throw TranslationLoaderException::malformedRow($this->table, $locale, column: 'translation');
            }

            $prefixed = $this->prefix->apply($key);

            if (!$this->keys->allows($prefixed)) {
                throw TranslationLoaderException::invalidDatabaseKey($this->table, $locale, $prefixed);
            }

            if (array_key_exists($prefixed, $messages)) {
                throw TranslationLoaderException::duplicateDatabaseKey($this->table, $locale, $key);
            }

            $messages[$prefixed] = $translation;
        }

        return new TranslationCatalogue($locale, $messages);
    }
}
