<?php

namespace Domains\Bakery\Application\Exports;

interface PeriodReportWorkbookInterface
{
    /**
     * @param  array{orders:list<array{date:string,store:string,product:string,quantity:int,returned:int,unit_kopecks:int,total_kopecks:int,return_kopecks:int,net_kopecks:int}>,products:array<string,int>,production:array{dough_grams:int|float,main_dough:array<string,float>,samsa_count:int,samsa_batch_size:int|float,samsa_dough:array<string,float>,ingredients:array<string,int|float>,other_ingredients:array<string,array<string,int|float>>,without_recipe:list<string>},total_kopecks:int,return_kopecks:int,net_kopecks:int}  $report
     * @param  list<array{date:string,item:string,unit:string,balance_milli:int,price_kopecks:int|null,measure_milli:int|null,measure_unit:string|null,value_kopecks:int|null}>  $stockRows
     */
    public function create(array $report, array $stockRows = []): string;
}
