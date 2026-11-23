<?php

namespace Domains\StockItem\Infrastructure\Repositories;

use Domains\Ingredient\Application\Repositories\IngredientRepositoryInterface;
use Domains\Product\Application\Repositories\ProductOrderReadRepositoryInterface;
use Domains\StockItem\Application\Repositories\StockItemRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockItemRepository implements StockItemRepositoryInterface
{
    public function __construct(
        private readonly IngredientRepositoryInterface $ingredients,
        private readonly ProductOrderReadRepositoryInterface $products,
    ) {}

    public function syncCatalog(): void
    {
        foreach ($this->ingredients->all() as $ingredient) {
            DB::table('bakery_stock_items')->insertOrIgnore([
                'kind' => 'ingredient',
                'ingredient_id' => $ingredient->id,
                'name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('bakery_stock_items')
                ->where('ingredient_id', $ingredient->id)
                ->where(function (Builder $query) use ($ingredient): void {
                    $query
                        ->where('name', '!=', $ingredient->name)
                        ->orWhere('unit', '!=', $ingredient->unit);
                })
                ->update([
                    'name' => $ingredient->name,
                    'unit' => $ingredient->unit,
                    'updated_at' => now(),
                ]);
        }
        foreach ($this->products->all() as $product) {
            DB::table('bakery_stock_items')->insertOrIgnore([
                'kind' => 'product',
                'product_id' => $product->id,
                'name' => $product->name,
                'unit' => 'pcs',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('bakery_stock_items')
                ->where('product_id', $product->id)
                ->where('name', '!=', $product->name)
                ->update(['name' => $product->name, 'updated_at' => now()]);
        }
    }

    public function createPackaging(string $name, string $unit): void
    {
        $name = trim($name);
        if (
            $name === '' ||
            DB::table('bakery_stock_items')
                ->where('kind', 'packaging')
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'name' => 'Упаковка с таким названием уже есть или название пустое.',
            ]);
        }
        DB::table('bakery_stock_items')->insert([
            'kind' => 'packaging',
            'name' => $name,
            'unit' => $unit,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findUnit(int $id): ?string
    {
        $unit = DB::table('bakery_stock_items')->where('id', $id)->value('unit');

        return is_string($unit) ? $unit : null;
    }
}
