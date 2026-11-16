<?php

namespace Domains\Bakery\Application;

use Domains\StoreGroup\Application\Repositories\GroupInvoiceReadRepositoryInterface;

/**
 * @phpstan-type GroupMember array{name:string,address:string,comment:string|null,ordered:bool,quantity:int,gross:int,returns:int,net:int}
 * @phpstan-type GroupProduct array{name:string,quantity:int,returned:int,unit_kopecks:int,gross:int,net:int}
 * @phpstan-type GroupInvoice array{id:int,name:string,members:list<GroupMember>,products:list<GroupProduct>,quantity:int,returned_quantity:int,gross:int,returns:int,net:int,has_orders:bool,show_return_columns:bool}
 * @deprecated Use Domains\StoreGroup\Application\GroupInvoiceService.
 */

class GroupInvoiceService extends \Domains\StoreGroup\Application\GroupInvoiceService
{
    public function __construct(
        OrderReturnSummary $returnSummary,
        ?GroupInvoiceReadRepositoryInterface $repository = null,
    ) {
        parent::__construct($returnSummary, $repository ?? app(GroupInvoiceReadRepositoryInterface::class));
    }
}
