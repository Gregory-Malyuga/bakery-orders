<?php

namespace Domains\Bakery\Infrastructure\Console;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Domains\Bakery\Infrastructure\Import\ReferenceWorkbookReader;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SeedReferenceCatalogCommand extends Command
{
    protected $signature = 'bakery:seed-reference {--dry-run : Только проверить XLSX без записи в БД}';

    protected $description = 'Загрузить магазины, товары, персональные цены и аккаунты из Лист1 файла Накладные 10.09.xlsx';

    public function handle(ReferenceWorkbookReader $reader): int
    {
        $path = resource_path('data/Накладные 10.09.xlsx');
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
        $this->info(count($data['stores']).' магазинов, '.count($data['products']).' товаров, '.(count($data['stores']) * count($data['products']) - $unavailable).' цен. Недоступных пар: '.$unavailable.'.');
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        try {
            $createdAccounts = DB::transaction(function () use ($data, $reader): int {
                $storesByName = [];
                foreach (Bakery::query()->get() as $store) {
                    $key = $reader->key($store->name);
                    if (isset($storesByName[$key])) {
                        throw new RuntimeException("В БД есть повторяющиеся названия магазинов: {$store->name}.");
                    }
                    $storesByName[$key] = $store;
                }
                $productsByName = [];
                foreach (Product::query()->get() as $product) {
                    $key = $reader->key($product->name);
                    if (isset($productsByName[$key])) {
                        throw new RuntimeException("В БД есть повторяющиеся названия товаров: {$product->name}.");
                    }
                    $productsByName[$key] = $product;
                }
                $storeIds = [];
                $createdAccounts = 0;
                foreach ($data['stores'] as $index => $name) {
                    $key = $reader->key($name);
                    $store = $storesByName[$key] ?? Bakery::query()->create(['name' => $name, 'address' => $name, 'is_active' => true]);
                    $store->update(['is_active' => true]);
                    $storeIds[] = $store->id;
                    $email = sprintf('store-%03d@bakery.test', $index + 1);
                    $account = User::query()->where('email', $email)->first();
                    if ($account === null) {
                        User::query()->create([
                            'name' => $name,
                            'email' => $email,
                            'password' => Hash::make('changeme'),
                            'role' => UserRole::User,
                            'status' => UserStatus::Verify,
                            'bakery_id' => $store->id,
                        ]);
                        $createdAccounts++;
                    } elseif ($account->role !== UserRole::User || (int) $account->bakery_id !== $store->id) {
                        throw new RuntimeException("Почта {$email} уже принадлежит другому аккаунту или магазину.");
                    }
                }
                $productIds = [];
                foreach ($data['products'] as $index => $name) {
                    $key = $reader->key($name);
                    $product = $productsByName[$key] ?? Product::query()->create(['name' => $name, 'sort_order' => $index + 1, 'minimum_quantity' => 1, 'is_active' => true]);
                    $product->update(['sort_order' => $index + 1, 'is_active' => true]);
                    $productIds[] = $product->id;
                }
                foreach ($data['prices'] as $productIndex => $row) {
                    foreach ($row as $storeIndex => $kopecks) {
                        $identity = ['bakery_id' => $storeIds[$storeIndex], 'product_id' => $productIds[$productIndex]];
                        if ($kopecks === null) {
                            Price::query()->where($identity)->delete();
                        } else {
                            Price::query()->updateOrCreate($identity, ['kopecks' => $kopecks]);
                        }
                    }
                }

                return $createdAccounts;
            });
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info("Готово. Создано аккаунтов: {$createdAccounts}. Логины store-001@bakery.test … store-072@bakery.test, пароль новых аккаунтов changeme.");
        $this->warn('Надписи из Лист1 использованы как название и адрес магазина; их можно уточнить в админ-панели. Демомагазины и демотовары сохранены.');

        return self::SUCCESS;
    }
}
