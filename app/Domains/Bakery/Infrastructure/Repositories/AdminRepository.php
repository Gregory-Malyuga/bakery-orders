<?php

namespace Domains\Bakery\Infrastructure\Repositories;

use Domains\Bakery\Application\Repositories\AdminRepositoryInterface;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
            Bakery::query()->whereKey($storeId)->lockForUpdate()->firstOrFail();
            Price::query()->updateOrCreate(['bakery_id' => $storeId, 'product_id' => $productId], ['kopecks' => $kopecks]);
        });
    }

    public function saveAccount(?int $id, int $storeId, string $name, string $email, ?string $password, bool $active): void
    {
        $data = ['bakery_id' => $storeId, 'name' => $name, 'email' => $email, 'role' => 'user', 'status' => $active ? 'verify' : 'banned'];
        if ($password !== null && $password !== '') {
            $data['password'] = Hash::make($password);
        }
        if ($id === null && ! isset($data['password'])) {
            abort(422, 'Пароль нового аккаунта обязателен.');
        }
        if ($id === null) {
            DB::table('users')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        } else {
            $changed = DB::table('users')->where('id', $id)->where('role', 'user')->update($data + ['updated_at' => now()]);
            abort_if($changed === 0, 404);
        }
    }

    public function accounts(): array
    {
        $accounts = [];
        foreach (DB::table('users')->where('role', 'user')->orderBy('name')->get(['id', 'bakery_id', 'name', 'email', 'status']) as $user) {
            /** @var object{id:int,bakery_id:int,name:string,email:string,status:string} $user */
            $accounts[] = ['id' => (int) $user->id, 'bakery_id' => (int) $user->bakery_id, 'name' => (string) $user->name, 'email' => (string) $user->email, 'status' => (string) $user->status];
        }

        return $accounts;
    }
}
