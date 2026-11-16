<?php

namespace Domains\StockPriceChange\Application\Commands\Save;

use Domains\StockItem\Application\Repositories\StockItemRepositoryInterface;
use Domains\StockPriceChange\Application\Repositories\StockPriceChangeRepositoryInterface;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class SaveStockPriceHandler implements CommandHandlerInterface
{
    public function __construct(
        private StockPriceChangeRepositoryInterface $repository,
        private StockItemRepositoryInterface $items,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SaveStockPriceCommand);

        $stockUnit = $this->items->findUnit($message->itemId);
        if ($stockUnit === null) {
            throw ValidationException::withMessages(['item' => 'Позиция склада не найдена.']);
        }

        $price = str_replace(',', '.', trim($message->price));
        if (! preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $price)) {
            throw ValidationException::withMessages(['price' => 'Укажите цену в рублях с точностью до копейки.']);
        }

        [$rubles, $kopecks] = array_pad(explode('.', $price, 2), 2, '');
        $priceKopecks = (int) $rubles * 100 + (int) str_pad($kopecks, 2, '0');
        if ($priceKopecks <= 0) {
            throw ValidationException::withMessages(['price' => 'Цена должна быть больше нуля.']);
        }

        [$quantity, $unit] = match ($message->measure) {
            '1g' => ['1', 'g'],
            '70g' => ['70', 'g'],
            '100g' => ['100', 'g'],
            '1kg' => ['1', 'kg'],
            '1ml' => ['1', 'ml'],
            '100ml' => ['100', 'ml'],
            '1l' => ['1', 'l'],
            '1unit' => ['1', $stockUnit],
            default => [$message->customQuantity ?? '', $message->customUnit ?? ''],
        };

        $baseUnit = match ($unit) {
            'kg' => 'g',
            'l' => 'ml',
            default => $unit,
        };
        $normalizedStockUnit = match ($stockUnit) {
            'kg' => 'g',
            'l' => 'ml',
            default => $stockUnit,
        };
        if ($baseUnit !== $normalizedStockUnit) {
            throw ValidationException::withMessages(['measure' => 'Мера цены должна соответствовать единице складской позиции.']);
        }

        $quantity = str_replace(',', '.', trim($quantity));
        if (! preg_match('/^\d{1,9}(?:\.\d{1,3})?$/', $quantity)) {
            throw ValidationException::withMessages(['custom_quantity' => 'Укажите положительную меру до трёх знаков после запятой.']);
        }

        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $measureMilli = ((int) $whole * 1000 + (int) str_pad($fraction, 3, '0'))
            * (in_array($unit, ['kg', 'l'], true) ? 1000 : 1);

        if ($measureMilli <= 0) {
            throw ValidationException::withMessages(['custom_quantity' => 'Мера должна быть больше нуля.']);
        }

        $effectiveDate = now('Europe/Moscow')->addDay()->toDateString();
        $previous = $this->repository->latestForDate($message->itemId, $effectiveDate);

        $this->repository->record([
            'stock_item_id' => $message->itemId,
            'effective_date' => $effectiveDate,
            'price_kopecks' => $priceKopecks,
            'measure_milli' => $measureMilli,
            'measure_unit' => $unit,
            'actor_id' => $message->actorId,
            'previous_price_kopecks' => $previous?->price_kopecks,
            'previous_measure_milli' => $previous?->measure_milli,
            'previous_measure_unit' => $previous?->measure_unit,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return null;
    }
}
