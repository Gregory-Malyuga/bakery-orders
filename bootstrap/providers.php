<?php

use Domains\Bakery\Providers\BakeryServiceProvider;
use Domains\DeliverySchedule\Providers\DeliveryScheduleServiceProvider;
use Domains\DeliverySheet\Providers\DeliverySheetServiceProvider;
use Domains\Ingredient\Providers\IngredientServiceProvider;
use Domains\Order\Providers\OrderServiceProvider;
use Domains\OrderAdjustment\Providers\OrderAdjustmentServiceProvider;
use Domains\OrderReturn\Providers\OrderReturnServiceProvider;
use Domains\StoreGroup\Providers\StoreGroupServiceProvider;
use Domains\Store\Providers\StoreServiceProvider;
use Domains\Recipe\Providers\RecipeServiceProvider;
use Domains\Production\Providers\ProductionServiceProvider;
use Domains\Product\Providers\ProductServiceProvider;
use Domains\Price\Providers\PriceServiceProvider;
use Domains\Label\Providers\LabelServiceProvider;
use Domains\Report\Providers\ReportServiceProvider;
use Domains\Invoice\Providers\InvoiceServiceProvider;
use Domains\Catalog\Providers\CatalogServiceProvider;
use Domains\StoreProductMinimum\Providers\StoreProductMinimumServiceProvider;
use Domains\StoreProductAvailability\Providers\StoreProductAvailabilityServiceProvider;
use Domains\StoreAccount\Providers\StoreAccountServiceProvider;
use Domains\StockItem\Providers\StockItemServiceProvider;
use Domains\StockMovement\Providers\StockMovementServiceProvider;
use Domains\StockPriceChange\Providers\StockPriceChangeServiceProvider;
use Domains\StockPrice\Providers\StockPriceServiceProvider;
use Domains\User\Providers\UserServiceProvider;
use Shared\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    UserServiceProvider::class,
    BakeryServiceProvider::class,
    DeliveryScheduleServiceProvider::class,
    DeliverySheetServiceProvider::class,
    IngredientServiceProvider::class,
    OrderServiceProvider::class,
    OrderAdjustmentServiceProvider::class,
    OrderReturnServiceProvider::class,
    StoreGroupServiceProvider::class,
    StoreServiceProvider::class,
    RecipeServiceProvider::class,
    ProductionServiceProvider::class,
    ProductServiceProvider::class,
    PriceServiceProvider::class,
    LabelServiceProvider::class,
    ReportServiceProvider::class,
    InvoiceServiceProvider::class,
    CatalogServiceProvider::class,
    StoreProductMinimumServiceProvider::class,
    StoreProductAvailabilityServiceProvider::class,
    StoreAccountServiceProvider::class,
    StockItemServiceProvider::class,
    StockMovementServiceProvider::class,
    StockPriceChangeServiceProvider::class,
    StockPriceServiceProvider::class,
];
