<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use Dirthara\Database\Database;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

use function sprintf;

final readonly class TranslationDatabase
{
    public ConnectedDatabase $database;

    public function __construct()
    {
        $manager = new ConnectionManager(
            new ConnectionFactory([new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()))]),
            [new ConnectionConfig(driver: DriverName::SQLite, name: 'translations', database: ':memory:')],
            default: 'translations',
        );

        $this->database = new Database($manager, new QueryGrammarResolver([
            DriverName::SQLite->value => new SQLiteQueryGrammar(),
        ]))->using();
    }

    public function createTable(string $table = 'translations'): void
    {
        $this->database->execute(sprintf('CREATE TABLE "%s" ("locale" TEXT, "key", "translation")', $table));
    }

    public function createCaseInsensitiveTable(string $table): void
    {
        $this->database->execute(sprintf(
            'CREATE TABLE "%s" ("locale" TEXT COLLATE NOCASE, "key", "translation")',
            $table,
        ));
    }

    public function insert(
        string $locale,
        string|int|null $key,
        string|int|null $translation,
        string $table = 'translations',
    ): void {
        $this->database->execute(
            sprintf('INSERT INTO "%s" ("locale", "key", "translation") VALUES (?, ?, ?)', $table),
            [
                $locale,
                $key,
                $translation,
            ],
        );
    }

    public function drop(string $table = 'translations'): void
    {
        $this->database->execute(sprintf('DROP TABLE "%s"', $table));
    }
}
