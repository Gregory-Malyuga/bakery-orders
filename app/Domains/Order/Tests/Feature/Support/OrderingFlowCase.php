<?php

namespace Domains\Order\Tests\Feature\Support;

use Carbon\Carbon;
use Domains\Price\Domain\Models\Price;
use Domains\Product\Domain\Models\Product;
use Domains\Store\Domain\Models\Store;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

abstract class OrderingFlowCase extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0:Store,1:User,2:Product} */
    protected function shop(string $name, int $kopecks): array
    {
        $store = Store::query()->create(['name' => $name, 'address' => $name.' 1', 'is_active' => true]);
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $store->id]);
        $product = Product::query()->firstOrCreate(['name' => 'Тестовый пирожок'], ['is_active' => true, 'packaging_mode' => 'single']);
        Price::query()->updateOrCreate(['bakery_id' => $store->id, 'product_id' => $product->id], ['kopecks' => $kopecks]);

        return [$store, $user, $product];
    }
}
