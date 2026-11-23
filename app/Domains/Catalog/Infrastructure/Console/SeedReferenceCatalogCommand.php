<?php

namespace Domains\Catalog\Infrastructure\Console;

use Domains\Catalog\Application\Commands\SeedReference\SeedReferenceCommand;
use Domains\Catalog\Application\Commands\SeedReference\SeedReferenceHandler;
use Domains\Catalog\Infrastructure\Import\ReferenceWorkbookReader;
use Illuminate\Console\Command;
use RuntimeException;

class SeedReferenceCatalogCommand extends Command
{
    public function __construct(private readonly SeedReferenceHandler $handler)
    {
        parent::__construct();
    }

    protected $signature = 'bakery:seed-reference {file : Путь к закрытому исходному XLSX} {--dry-run : Только проверить XLSX без записи в БД}';

    protected $description = 'Загрузить магазины, товары, персональные цены и аккаунты из листа «Лист1» закрытого XLSX';

    public function handle(ReferenceWorkbookReader $reader): int
    {
        $path = (string) $this->argument('file');
        try {
            $data = $reader->read($path);
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $unavailable = 0;
        foreach ($data['prices'] as $row) {
            $unavailable += count(array_filter($row, fn (?int $price): bool => $price === null));
        }
        $this->info(
            count($data['stores']).
                ' магазинов, '.
                count($data['products']).
                ' товаров, '.
                (count($data['stores']) * count($data['products']) - $unavailable).
                ' цен. Недоступных пар: '.
                $unavailable.
                '.',
        );
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $initialPassword = $this->secret(
            'Пароль для новых аккаунтов магазинов (не менее 12 символов)',
        );
        if (! is_string($initialPassword) || mb_strlen($initialPassword) < 12) {
            $this->error('Пароль должен содержать не менее 12 символов.');

            return self::FAILURE;
        }

        try {
            $createdAccounts = $this->handler->handle(
                new SeedReferenceCommand($data, $initialPassword),
            );
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info(
            "Готово. Создано аккаунтов: {$createdAccounts}. Логины store-001@bakery.test … store-072@bakery.test.",
        );
        $this->warn(
            'Надписи из Лист1 использованы как название и адрес магазина; их можно уточнить в админ-панели. Демомагазины и демотовары сохранены.',
        );

        return self::SUCCESS;
    }
}
