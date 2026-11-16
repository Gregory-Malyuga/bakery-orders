<?php

namespace Domains\Price\Presentation\Http\Controllers;

use Domains\Price\Application\Commands\BulkPrice\BulkPriceCommand;
use Domains\Price\Application\Commands\BulkPrice\BulkPriceHandler;
use Domains\Price\Application\Commands\ReversePrice\ReversePriceCommand;
use Domains\Price\Application\Commands\ReversePrice\ReversePriceHandler;
use Domains\Price\Application\Commands\SetPrice\SetPriceCommand;
use Domains\Price\Application\Commands\SetPrice\SetPriceHandler;
use Domains\Price\Application\Support\Kopecks;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class PriceController
{
    public function __construct(
        private SetPriceHandler $setPrice,
        private BulkPriceHandler $bulkPrice,
        private ReversePriceHandler $reversePrice,
    ) {}

    public function save(Request $request): RedirectResponse
    {
        /** @var array{store:int|string,product:int|string,rubles:string} $data */
        $data = $request->validate([
            'store' => ['required', 'integer', 'exists:bakeries,id'],
            'product' => ['required', 'integer', 'exists:bakery_products,id'],
            'rubles' => ['required', 'regex:/^\d{1,8}(?:[.,]\d{1,2})?$/'],
        ]);

        $this->setPrice->handle(new SetPriceCommand(
            (int) $data['product'],
            (int) $data['store'],
            Kopecks::fromRubles($data['rubles']),
            $this->actorId($request),
        ));

        return redirect()->route('bakery.catalog', ['tab' => 'prices', 'store' => $data['store']])->with('success', 'Цена сохранена.');
    }

    public function bulk(Request $request, int $product): RedirectResponse
    {
        /** @var array{stores:list<int|string>,direction:string,rubles:string,request_key:string} $data */
        $data = $request->validate([
            'stores' => ['required', 'array', 'min:1'],
            'stores.*' => ['required', 'integer', 'distinct', 'exists:bakeries,id'],
            'direction' => ['required', 'in:increase,decrease'],
            'rubles' => ['required', 'regex:/^\d{1,8}(?:[.,]\d{1,2})?$/'],
            'request_key' => ['required', 'uuid'],
        ]);

        $this->bulkPrice->handle(new BulkPriceCommand(
            $product,
            array_map('intval', $data['stores']),
            Kopecks::fromRubles($data['rubles']),
            $data['direction'],
            $data['request_key'],
            $this->actorId($request),
        ));

        return redirect()->route('bakery.product', $product)->with('success', 'Цены изменены.');
    }

    public function reverse(Request $request, int $product, int $operation): RedirectResponse
    {
        /** @var array{reason:string} $data */
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->reversePrice->handle(new ReversePriceCommand($product, $operation, $data['reason'], $this->actorId($request)));

        return redirect()->route('bakery.product', $product)->with('success', 'Изменение отменено.');
    }

    private function actorId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return (int) $user->id;
    }
}
