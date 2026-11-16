<?php

namespace Domains\Bakery\Infrastructure\Repositories;

use Domains\Bakery\Application\Repositories\AdminRepositoryInterface;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminRepository implements AdminRepositoryInterface
{
    public function saveStore(?int $id, array $data): void
    {
        Bakery::query()->updateOrCreate(['id' => $id], $data);
    }

    public function saveProduct(?int $id, array $data): void
    {
        Product::query()->updateOrCreate(['id' => $id], $data);
    }

    public function savePrice(int $storeId, int $productId, int $kopecks): void
    {
        DB::transaction(function () use ($storeId, $productId, $kopecks): void {
            $store = Bakery::query()->whereKey($storeId)->lockForUpdate()->firstOrFail();
            $priceStoreId = (int) ($store->price_source_id ?: $storeId);
            if ($priceStoreId !== $storeId) {
                Bakery::query()->whereKey($priceStoreId)->lockForUpdate()->firstOrFail();
            }
            Price::query()->updateOrCreate(['bakery_id' => $priceStoreId, 'product_id' => $productId], ['kopecks' => $kopecks]);
        });
    }

    public function saveAccount(?int $id, array $storeIds, string $name, string $email, ?string $password, bool $active): void
    {
        $data = ['bakery_id' => $storeIds[0], 'name' => $name, 'email' => $email, 'role' => 'user', 'status' => $active ? 'verify' : 'banned'];
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
                $id = DB::table('users')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
            } else {
                $account = DB::table('users')->where('id', $id)->where('role', 'user')->lockForUpdate()->first();
                abort_if($account === null, 404);
                /** @var object{bakery_id:int|string} $account */
                $oldPrimary = (int) $account->bakery_id;
                foreach (DB::table('bakery_account_stores')->where('user_id', $id)->pluck('bakery_id') as $value) {
                    if (is_int($value) || is_string($value)) {
                        $oldStores[] = (int) $value;
                    }
                }
                DB::table('users')->where('id', $id)->update($data + ['updated_at' => now()]);
                DB::table('bakery_account_stores')->where('user_id', $id)->delete();
            }
            $otherUsers = DB::table('bakery_account_stores')->whereIn('bakery_id', $storeIds)->where('user_id', '!=', $id)->distinct()->pluck('user_id');
            foreach ($otherUsers as $otherUserId) {
                if (DB::table('bakery_account_stores')->where('user_id', $otherUserId)->count() > 1) {
                    throw ValidationException::withMessages(['stores' => 'Один из магазинов уже входит в другую точку с общим прайсом.']);
                }
            }
            if ($oldPrimary !== null && count($oldStores) > 1) {
                $sharedPrices = Price::query()->where('bakery_id', $oldPrimary)->get();
                foreach ($oldStores as $oldStoreId) {
                    if ($oldStoreId === $oldPrimary) {
                        continue;
                    }
                    Price::query()->where('bakery_id', $oldStoreId)->delete();
                    foreach ($sharedPrices as $price) {
                        Price::query()->create(['bakery_id' => $oldStoreId, 'product_id' => $price->product_id, 'kopecks' => $price->kopecks]);
                    }
                }
                DB::table('bakeries')->whereIn('id', $oldStores)->where('price_source_id', $oldPrimary)->update(['price_source_id' => null]);
            }
            DB::table('bakery_account_stores')->insert(array_map(fn (int $storeId): array => ['user_id' => $id, 'bakery_id' => $storeId], $storeIds));
            if (count($storeIds) > 1) {
                DB::table('bakeries')->where('id', $storeIds[0])->update(['price_source_id' => null]);
                DB::table('bakeries')->whereIn('id', array_slice($storeIds, 1))->update(['price_source_id' => $storeIds[0]]);
            }
        });
    }

    public function accounts(): array
    {
        $accounts = [];
        foreach (DB::table('users')->where('role', 'user')->orderBy('name')->get(['id', 'bakery_id', 'name', 'email', 'status']) as $user) {
            /** @var object{id:int,bakery_id:int,name:string,email:string,status:string} $user */
            $storeIds = [];
            foreach (DB::table('bakery_account_stores')->where('user_id', $user->id)->pluck('bakery_id') as $storeId) {
                if (is_int($storeId) || is_string($storeId)) {
                    $storeIds[] = (int) $storeId;
                }
            }
            $accounts[] = ['id' => (int) $user->id, 'bakery_id' => (int) $user->bakery_id, 'store_ids' => $storeIds ?: [(int) $user->bakery_id], 'name' => (string) $user->name, 'email' => (string) $user->email, 'status' => (string) $user->status];
        }

        return $accounts;
    }
}
