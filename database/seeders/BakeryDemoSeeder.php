<?php

namespace Database\Seeders;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BakeryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $products = ['Сосиска в тесте', 'Пирожок с капустой', 'Пирожок с картофелем', 'Самса с курицей', 'Беляш', 'Пирожок с яйцом', 'Ватрушка', 'Булочка с корицей'];
        $stores = [
            ['name' => 'Магазин на Садовой', 'address' => 'ул. Садовая, 16', 'email' => 'sadovaya@example.test', 'offset' => 0],
            ['name' => 'Кофейня на Фрунзе', 'address' => 'ул. Фрунзе, 23', 'email' => 'frunze@example.test', 'offset' => 300],
            ['name' => 'Магазин у шоссе', 'address' => 'Красносельское шоссе, 55А', 'email' => 'shosse@example.test', 'offset' => 150],
        ];
        foreach ($products as $position => $name) {
            Product::query()->updateOrCreate(['name' => $name], ['sort_order' => $position + 1, 'is_active' => true]);
        }
        foreach ($stores as $storeData) {
            $store = Bakery::query()->updateOrCreate(['name' => $storeData['name'], 'address' => $storeData['address']], ['is_active' => true]);
            DB::table('users')->updateOrInsert(['email' => $storeData['email']], ['name' => $storeData['name'], 'bakery_id' => $store->id, 'password' => Hash::make('Demo123456!'), 'role' => 'user', 'status' => 'verify', 'updated_at' => now(), 'created_at' => now()]);
            foreach ($products as $position => $name) {
                $product = Product::query()->where('name', $name)->firstOrFail();
                Price::query()->updateOrCreate(['bakery_id' => $store->id, 'product_id' => $product->id], ['kopecks' => 2900 + $position * 300 + $storeData['offset']]);
            }
        }
        DB::table('users')->updateOrInsert(['email' => 'admin@example.test'], ['name' => 'Администратор пекарни', 'bakery_id' => null, 'password' => Hash::make('Demo123456!'), 'role' => 'admin', 'status' => 'verify', 'updated_at' => now(), 'created_at' => now()]);
    }
}
