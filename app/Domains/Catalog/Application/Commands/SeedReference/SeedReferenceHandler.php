<?php

namespace Domains\Catalog\Application\Commands\SeedReference;

use Domains\Catalog\Application\Repositories\TransactionRunnerInterface;
use Domains\Catalog\Application\Support\CatalogNameNormalizer;
use Domains\Price\Application\Repositories\PriceReferenceImportRepositoryInterface;
use Domains\Product\Application\Repositories\ProductReferenceImportRepositoryInterface;
use Domains\Store\Application\Repositories\StoreReferenceImportRepositoryInterface;
use Domains\StoreAccount\Application\Repositories\StoreAccountMembershipRepositoryInterface;
use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Shared\Bus\CommandHandlerInterface;

readonly class SeedReferenceHandler implements CommandHandlerInterface
{
    public function __construct(
        private TransactionRunnerInterface $transactions,
        private CatalogNameNormalizer $normalizer,
        private StoreReferenceImportRepositoryInterface $stores,
        private ProductReferenceImportRepositoryInterface $products,
        private PriceReferenceImportRepositoryInterface $prices,
        private UserRepositoryInterface $users,
        private StoreAccountMembershipRepositoryInterface $memberships,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SeedReferenceCommand);

        $createdAccounts = 0;
        $this->transactions->run(function () use ($message, &$createdAccounts): void {
            $storesByName = [];
            foreach ($this->stores->all() as $store) {
                $key = $this->normalizer->key($store->name);
                if (isset($storesByName[$key])) {
                    throw new RuntimeException("В БД есть повторяющиеся названия магазинов: {$store->name}.");
                }
                $storesByName[$key] = $store;
            }

            $productsByName = [];
            foreach ($this->products->all() as $product) {
                $key = $this->normalizer->key($product->name);
                if (isset($productsByName[$key])) {
                    throw new RuntimeException("В БД есть повторяющиеся названия товаров: {$product->name}.");
                }
                $productsByName[$key] = $product;
            }

            $storeIds = [];
            foreach ($message->data['stores'] as $index => $name) {
                $key = $this->normalizer->key($name);
                $store = $storesByName[$key] ?? $this->stores->activateOrCreate($name, $name);
                $this->stores->activate((int) $store->id);
                $storeIds[] = (int) $store->id;

                $email = sprintf('store-%03d@bakery.test', $index + 1);
                $user = $this->users->findByEmail($email);
                if ($user === null) {
                    $user = $this->users->create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($message->initialPassword),
                        'role' => UserRole::User,
                        'status' => UserStatus::Verify,
                        'bakery_id' => $store->id,
                    ]);
                    $createdAccounts++;
                } elseif ($user->role !== UserRole::User || (int) $user->bakery_id !== (int) $store->id) {
                    throw new RuntimeException("Почта {$email} уже принадлежит другому аккаунту или магазину.");
                }

                $this->memberships->ensureMember((int) $user->id, (int) $store->id);
            }

            $productIds = [];
            foreach ($message->data['products'] as $index => $name) {
                $key = $this->normalizer->key($name);
                $mode = in_array($key, ['пицца', 'ватрушка', 'хачапури', 'лепёшка', 'лепешка'], true)
                    ? 'single'
                    : (in_array($key, ['мак', 'изюм', 'косичка с маком'], true) ? 'pair' : 'mixed');
                $minimum = $mode === 'mixed' ? 3 : ($mode === 'pair' ? 2 : 1);
                $product = $productsByName[$key] ?? $this->products->activateOrCreate($name, $index + 1, $mode, $minimum);
                $this->products->activate((int) $product->id, $index + 1);
                $productIds[] = (int) $product->id;
            }

            foreach ($message->data['prices'] as $productIndex => $row) {
                foreach ($row as $storeIndex => $kopecks) {
                    $this->prices->setOrDelete($storeIds[$storeIndex], $productIds[$productIndex], $kopecks);
                }
            }
        });

        return $createdAccounts;
    }
}
