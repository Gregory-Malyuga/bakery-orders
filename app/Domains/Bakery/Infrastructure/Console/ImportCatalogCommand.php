<?php

namespace Domains\Bakery\Infrastructure\Console;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCatalogCommand extends Command
{
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
        DB::transaction(function () use ($stores, $products, $rows): void {
            $storeIds = [];
            $productIds = [];
            foreach ($stores as $key => [$name, $address]) {
                $storeIds[$key] = Bakery::query()->firstOrCreate(['name' => $name, 'address' => $address], ['is_active' => true])->id;
            }
            foreach ($products as $key => $name) {
                $productIds[$key] = Product::query()->firstOrCreate(['name' => $name], ['is_active' => true])->id;
            }
            foreach ($rows as [$storeKey, $productKey, $kopecks]) {
                Price::query()->updateOrCreate(['bakery_id' => $storeIds[$storeKey], 'product_id' => $productIds[$productKey]], ['kopecks' => $kopecks]);
            }
        });
        $this->info('Импорт завершён.');

        return self::SUCCESS;
    }
}
