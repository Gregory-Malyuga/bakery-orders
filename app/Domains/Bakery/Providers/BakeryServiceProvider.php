<?php

namespace Domains\Bakery\Providers;

use Domains\Bakery\Application\Commands\Create\CreateBakeryHandler;
use Domains\Bakery\Application\Commands\Delete\DeleteBakeryHandler;
use Domains\Bakery\Application\Commands\Update\UpdateBakeryHandler;
use Domains\Bakery\Application\Exports\InvoiceWorkbookInterface;
use Domains\Bakery\Application\Exports\PeriodReportWorkbookInterface;
use Domains\Bakery\Application\IngredientCatalog;
use Domains\Bakery\Application\ProductionCalculator;
use Domains\Bakery\Application\ProductionRecipeStore;
use Domains\Bakery\Application\Queries\FindById\FindBakeryByIdHandler;
use Domains\Bakery\Application\Queries\ListAll\ListBakerysHandler;
use Domains\Bakery\Application\Repositories\AdminRepositoryInterface;
use Domains\Bakery\Application\Repositories\BakeryRepositoryInterface;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Bakery\Infrastructure\Console\CreateAdminCommand;
use Domains\Bakery\Infrastructure\Console\ImportCatalogCommand;
use Domains\Bakery\Infrastructure\Console\SeedReferenceCatalogCommand;
use Domains\Bakery\Infrastructure\Exports\MatrixInvoiceWorkbook;
use Domains\Bakery\Infrastructure\Exports\PeriodReportWorkbook;
use Domains\Bakery\Infrastructure\Repositories\AdminRepository;
use Domains\Bakery\Infrastructure\Repositories\BakeryRepository;
use Domains\Bakery\Infrastructure\Repositories\OrderingRepository;
use Illuminate\Support\ServiceProvider;

class BakeryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CreateBakeryHandler::class);
        $this->app->bind(UpdateBakeryHandler::class);
        $this->app->bind(DeleteBakeryHandler::class);
        $this->app->bind(ListBakerysHandler::class);
        $this->app->bind(FindBakeryByIdHandler::class);

        $this->app->bind(BakeryRepositoryInterface::class, BakeryRepository::class);
        $this->app->bind(OrderingRepositoryInterface::class, OrderingRepository::class);
        $this->app->bind(AdminRepositoryInterface::class, AdminRepository::class);
        $this->app->bind(InvoiceWorkbookInterface::class, MatrixInvoiceWorkbook::class);
        $this->app->bind(PeriodReportWorkbookInterface::class, PeriodReportWorkbook::class);
        $this->app->bind(ProductionCalculator::class, fn ($app): ProductionCalculator => new ProductionCalculator($app->make(ProductionRecipeStore::class), $app->make(IngredientCatalog::class)));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportCatalogCommand::class, SeedReferenceCatalogCommand::class, CreateAdminCommand::class]);
        }
    }
}
