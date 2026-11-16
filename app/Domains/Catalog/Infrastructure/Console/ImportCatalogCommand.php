<?php

namespace Domains\Catalog\Infrastructure\Console;

use Domains\Catalog\Application\Commands\Import\ImportCatalogCommand as ImportCommand;
use Domains\Catalog\Application\Commands\Import\ImportCatalogHandler;
use Illuminate\Console\Command;

class ImportCatalogCommand extends Command
{
    public function __construct(private readonly ImportCatalogHandler $importer)
    {
        parent::__construct();
    }

    protected $signature = 'bakery:import-catalog {file : UTF-8 CSV с разделителем ;} {--apply : Сохранить после проверки}';

    protected $description = 'Проверить или разово импортировать согласованные товары, магазины и цены';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || ($handle = fopen($path, 'rb')) === false) {
            $this->error('Файл не найден.');

            return self::FAILURE;
        }
        $header = fgetcsv($handle, 0, ';');
        if ($header !== ['product', 'store', 'address', 'price']) {
            fclose($handle);
            $this->error('Ожидаются колонки: product;store;address;price');

            return self::FAILURE;
        }
        $rows = [];
        $pairs = [];
        $stores = [];
        $products = [];
        $errors = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            if (count($values) !== 4) {
                $errors[] = "Строка {$line}: неверное число колонок.";

                continue;
            }
            [$product, $store, $address, $price] = array_map(fn (?string $value): string => trim($value ?? ''), $values);
            if ($product === '' || $store === '' || $address === '' || ! preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/', $price)) {
                $errors[] = "Строка {$line}: пропуск или некорректная цена.";

                continue;
            }
            $storeKey = mb_strtolower($store.'|'.$address);
            $productKey = mb_strtolower($product);
            $pair = $storeKey.'|'.$productKey;
            if (isset($pairs[$pair])) {
                $errors[] = "Строка {$line}: повтор товара и магазина.";
            }
            $pairs[$pair] = true;
            if (isset($products[$productKey]) && $products[$productKey] !== $product) {
                $errors[] = "Строка {$line}: неоднозначное написание товара {$product}.";
            }
            $products[$productKey] = $product;
            $stores[$storeKey] = [$store, $address];
            [$rubles, $kopecks] = array_pad(explode('.', str_replace(',', '.', $price)), 2, '0');
            $rows[] = [$storeKey, $productKey, (int) $rubles * 100 + (int) str_pad($kopecks, 2, '0')];
        }
        fclose($handle);
        foreach (array_keys($stores) as $storeKey) {
            foreach (array_keys($products) as $productKey) {
                if (! isset($pairs[$storeKey.'|'.$productKey])) {
                    $errors[] = "Отсутствует цена: {$stores[$storeKey][0]} / {$products[$productKey]}.";
                }
            }
        }
        if ($errors !== [] || $rows === []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            if ($rows === []) {
                $this->error('Нет строк для импорта.');
            }

            return self::FAILURE;
        }
        $this->info(count($stores).' магазинов, '.count($products).' товаров, '.count($rows).' цен. Проверка пройдена.');
        if (! $this->option('apply')) {
            return self::SUCCESS;
        }
        $this->importer->handle(new ImportCommand($stores, $products, $rows));
        $this->info('Импорт завершён.');

        return self::SUCCESS;
    }
}
