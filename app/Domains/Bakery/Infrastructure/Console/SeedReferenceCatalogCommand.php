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
        $this->info(count($data['stores']).' магазинов, '.count($data['products']).' товаров, '.(count($data['stores']) * count($data['products']) - $unavailable).' цен. Недоступных пар: '.$unavailable.'.');
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $initialPassword = $this->secret('Пароль для новых аккаунтов магазинов (не менее 12 символов)');
        if (! is_string($initialPassword) || mb_strlen($initialPassword) < 12) {
            $this->error('Пароль должен содержать не менее 12 символов.');

            return self::FAILURE;
        }

        try {
            $createdAccounts = DB::transaction(function () use ($data, $reader, $initialPassword): int {
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
                        $account = User::query()->create([
                            'name' => $name,
                            'email' => $email,
                            'password' => Hash::make($initialPassword),
                            'role' => UserRole::User,
                            'status' => UserStatus::Verify,
                            'bakery_id' => $store->id,
                        ]);
                        $createdAccounts++;
                    } elseif ($account->role !== UserRole::User || (int) $account->bakery_id !== $store->id) {
                        throw new RuntimeException("Почта {$email} уже принадлежит другому аккаунту или магазину.");
                    }
                    DB::table('bakery_account_stores')->updateOrInsert(['user_id' => $account->id, 'bakery_id' => $store->id]);
                }
                $productIds = [];
                foreach ($data['products'] as $index => $name) {
                    $key = $reader->key($name);
                    $mode = in_array($key, ['пицца', 'ватрушка', 'хачапури', 'лепёшка', 'лепешка'], true) ? 'single' : (in_array($key, ['мак', 'изюм', 'косичка с маком'], true) ? 'pair' : 'mixed');
                    $minimum = $mode === 'mixed' ? 3 : ($mode === 'pair' ? 2 : 1);
                    $product = $productsByName[$key] ?? Product::query()->create(['name' => $name, 'sort_order' => $index + 1, 'minimum_quantity' => $minimum, 'packaging_mode' => $mode, 'is_active' => true]);
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
        $this->info("Готово. Создано аккаунтов: {$createdAccounts}. Логины store-001@bakery.test … store-072@bakery.test.");
        $this->warn('Надписи из Лист1 использованы как название и адрес магазина; их можно уточнить в админ-панели. Демомагазины и демотовары сохранены.');

        return self::SUCCESS;
    }
}
