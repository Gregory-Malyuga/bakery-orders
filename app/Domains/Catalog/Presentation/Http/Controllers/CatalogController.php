<?php

namespace Domains\Catalog\Presentation\Http\Controllers;

use Domains\Catalog\Application\Data\CatalogPageData;
use Domains\Catalog\Application\Data\CatalogProductData;
use Domains\Catalog\Application\Queries\Catalog\GetCatalogHandler;
use Domains\Catalog\Application\Queries\Catalog\GetCatalogQuery;
use Domains\Catalog\Application\Queries\Product\GetCatalogProductHandler;
use Domains\Catalog\Application\Queries\Product\GetCatalogProductQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

readonly class CatalogController
{
    public function __construct(
        private GetCatalogHandler $catalog,
        private GetCatalogProductHandler $product,
    ) {}

    public function catalog(Request $request): View
    {
        /** @var array{store?:int|string,tab?:string,page?:int|string,search?:string} $input */
        $input = $request->validate([
            'store' => ['nullable', 'integer'],
            'tab' => ['nullable', 'in:stores,products,prices,accounts,settings'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->renderCatalog($this->catalog->handle(new GetCatalogQuery(
            isset($input['store']) ? (int) $input['store'] : null,
            $input['tab'] ?? 'stores',
            isset($input['page']) ? (int) $input['page'] : 1,
            $input['search'] ?? '',
        )));
    }

    public function product(int $product): View
    {
        return $this->renderProduct($this->product->handle(new GetCatalogProductQuery($product)));
    }

    private function renderCatalog(CatalogPageData $data): View
    {
        return view('bakery.catalog', $data->viewData());
    }

    private function renderProduct(CatalogProductData $data): View
    {
        return view('bakery.product', $data->viewData());
    }
}
