<?php

namespace Domains\Production\Presentation\Http\Controllers;

use Domains\Production\Application\Queries\Dashboard\ProductionDashboardHandler;
use Domains\Production\Application\Queries\Dashboard\ProductionDashboardQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

readonly class ProductionDashboardController
{
    public function __construct(private ProductionDashboardHandler $dashboard) {}

    public function index(Request $request): View
    {
        /** @var array{date?:string,tab?:string,page?:int|string} $input */
        $input = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'tab' => ['nullable', 'in:orders,production,packaging,labels,prep'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return view('bakery.admin', $this->dashboard->handle(new ProductionDashboardQuery(
            $input['date'] ?? now('Europe/Moscow')->addDay()->toDateString(),
            $input['tab'] ?? 'orders',
            isset($input['page']) ? (int) $input['page'] : 1,
        ))->view);
    }
}
