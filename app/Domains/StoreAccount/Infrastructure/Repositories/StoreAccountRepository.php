<?php

namespace Domains\StoreAccount\Infrastructure\Repositories;

use Domains\Price\Application\Repositories\PriceAccountMembershipRepositoryInterface;
use Domains\Store\Application\Repositories\StoreAccountPriceSourceRepositoryInterface;
use Domains\StoreAccount\Application\Repositories\StoreAccountRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class StoreAccountRepository implements StoreAccountRepositoryInterface
{
    public function __construct(
        private readonly PriceAccountMembershipRepositoryInterface $priceMembership,
        private readonly StoreAccountPriceSourceRepositoryInterface $storePriceSources,
    ) {}

    public function saveAccount(
        ?int $id,
        array $storeIds,
        string $name,
        string $email,
        ?string $password,
        bool $active,
    ): void {
        $data = [
            'bakery_id' => $storeIds[0] ?? null,
            'name' => $name,
            'email' => $email,
            'role' => 'user',
            'status' => $active ? 'verify' : 'banned',
        ];
        if ($password !== null && $password !== '') {
            $data['password'] = Hash::make($password);
        }
        if ($id === null && ! isset($data['password'])) {
            abort(422, 'Пароль нового аккаунта обязателен.');
        }
        DB::transaction(function () use ($id, $data, $storeIds): void {
            $oldPrimary = null;
            $oldStores = [];
            if ($id === null) {
                $id = DB::table('users')->insertGetId(
                    $data + ['created_at' => now(), 'updated_at' => now()],
                );
            } else {
                $account = DB::table('users')
                    ->where('id', $id)
                    ->where('role', 'user')
                    ->lockForUpdate()
                    ->first();
                abort_if($account === null, 404);
                /** @var object{bakery_id:int|string} $account */
                $oldPrimary = (int) $account->bakery_id;
                foreach (
                    DB::table('bakery_account_stores')->where('user_id', $id)->pluck('bakery_id') as $value
                ) {
                    if (is_int($value) || is_string($value)) {
                        $oldStores[] = (int) $value;
                    }
                }
                DB::table('users')
                    ->where('id', $id)
                    ->update($data + ['updated_at' => now()]);
                DB::table('bakery_account_stores')->where('user_id', $id)->delete();
            }
            if (
                DB::table('bakery_account_stores')
                    ->whereIn('bakery_id', $storeIds)
                    ->where('user_id', '!=', $id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'stores' => 'Один из магазинов уже привязан к другому аккаунту. Сначала отвяжите его там.',
                ]);
            }
            if ($oldPrimary !== null && count($oldStores) > 1) {
                $this->priceMembership->copySharedPricesToStores($oldPrimary, $oldStores);
                $this->storePriceSources->detachFromSharedPriceSource($oldStores, $oldPrimary);
            }
            if ($storeIds !== []) {
                DB::table('bakery_account_stores')->insert(
                    array_map(
                        fn (int $storeId): array => ['user_id' => $id, 'bakery_id' => $storeId],
                        $storeIds,
                    ),
                );
            }
            if (count($storeIds) > 1) {
                $this->storePriceSources->sharePriceSource($storeIds);
            }
        });
    }

    public function accounts(): array
    {
        $accounts = [];
        foreach (
            DB::table('users')
                ->where('role', 'user')
                ->orderBy('name')
                ->get(['id', 'bakery_id', 'name', 'email', 'status']) as $user
        ) {
            /** @var object{id:int,bakery_id:int|null,name:string,email:string,status:string} $user */
            $storeIds = [];
            foreach (
                DB::table('bakery_account_stores')->where('user_id', $user->id)->pluck('bakery_id') as $storeId
            ) {
                if (is_int($storeId) || is_string($storeId)) {
                    $storeIds[] = (int) $storeId;
                }
            }
            $accounts[] = [
                'id' => (int) $user->id,
                'bakery_id' => $user->bakery_id === null ? null : (int) $user->bakery_id,
                'store_ids' => $storeIds ?: ($user->bakery_id === null ? [] : [(int) $user->bakery_id]),
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'status' => (string) $user->status,
            ];
        }

        return $accounts;
    }
}
