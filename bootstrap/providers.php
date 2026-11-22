<?php

use Domains\Catalog\Providers\CatalogServiceProvider;
use Domains\DeliverySchedule\Providers\DeliveryScheduleServiceProvider;
use Domains\DeliverySheet\Providers\DeliverySheetServiceProvider;
use Domains\Ingredient\Providers\IngredientServiceProvider;
use Domains\Invoice\Providers\InvoiceServiceProvider;
use Domains\Label\Providers\LabelServiceProvider;
use Domains\Order\Providers\OrderServiceProvider;
use Domains\OrderAdjustment\Providers\OrderAdjustmentServiceProvider;
use Domains\OrderReturn\Providers\OrderReturnServiceProvider;
use Domains\Price\Providers\PriceServiceProvider;
use Domains\Product\Providers\ProductServiceProvider;
use Domains\Production\Providers\ProductionServiceProvider;
use Domains\Recipe\Providers\RecipeServiceProvider;
use Domains\Report\Providers\ReportServiceProvider;
use Domains\StockItem\Providers\StockItemServiceProvider;
use Domains\StockMovement\Providers\StockMovementServiceProvider;
use Domains\StockPrice\Providers\StockPriceServiceProvider;
use Domains\StockPriceChange\Providers\StockPriceChangeServiceProvider;
use Domains\Store\Providers\StoreServiceProvider;
use Domains\StoreAccount\Providers\StoreAccountServiceProvider;
use Domains\StoreGroup\Providers\StoreGroupServiceProvider;
use Domains\StoreProductAvailability\Providers\StoreProductAvailabilityServiceProvider;
use Domains\StoreProductMinimum\Providers\StoreProductMinimumServiceProvider;
use Domains\User\Providers\UserServiceProvider;
use Shared\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    UserServiceProvider::class,
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
