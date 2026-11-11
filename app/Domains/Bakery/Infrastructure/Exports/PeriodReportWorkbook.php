<?php

namespace Domains\Bakery\Infrastructure\Exports;

use Domains\Bakery\Application\Exports\PeriodReportWorkbookInterface;
use Domains\Bakery\Application\WeightFormatter;
use Phar;
use PharData;
use RuntimeException;

class PeriodReportWorkbook implements PeriodReportWorkbookInterface
{
    /**
     * @param  array{orders:list<array{date:string,store:string,product:string,quantity:int,returned:int,unit_kopecks:int,total_kopecks:int,return_kopecks:int,net_kopecks:int}>,products:array<string,int>,production:array{dough_grams:int|float,main_dough:array<string,float>,samsa_count:int,samsa_batch_size:int|float,samsa_dough:array<string,float>,ingredients:array<string,int|float>,other_ingredients:array<string,array<string,int|float>>,without_recipe:list<string>},total_kopecks:int,return_kopecks:int,net_kopecks:int}  $report
     * @param  list<array{date:string,item:string,unit:string,balance_milli:int,price_kopecks:int|null,measure_milli:int|null,measure_unit:string|null,value_kopecks:int|null}>  $stockRows
     */
    public function create(array $report, array $stockRows = []): string
    {
        $orders = [['Дата поставки', 'Магазин', 'Товар', 'Заказано, шт.', 'Возврат, шт.', 'Цена, ₽', 'Сумма, ₽', 'Возврат, ₽', 'После возврата, ₽']];
        foreach ($report['orders'] as $line) {
            $orders[] = [$line['date'], $line['store'], $line['product'], $line['quantity'], $line['returned'], $line['unit_kopecks'] / 100, $line['total_kopecks'] / 100, $line['return_kopecks'] / 100, $line['net_kopecks'] / 100];
        }
        $products = [['Изделие', 'Заказано, шт.']];
        foreach ($report['products'] as $name => $quantity) {
            $products[] = [$name, $quantity];
        }
        $ingredients = [['Раздел', 'Сырьё', 'Количество', 'Единица']];
        foreach (['main_dough' => 'Основное тесто', 'samsa_dough' => 'Тесто для самсы', 'ingredients' => 'Начинки и заготовки'] as $key => $section) {
            foreach ($report['production'][$key] as $name => $grams) {
                $ingredients[] = [$section, $name, WeightFormatter::kilograms($grams), 'кг'];
            }
        }
        foreach ($report['production']['other_ingredients'] as $unit => $items) {
            foreach ($items as $name => $quantity) {
                $ingredients[] = ['Начинки и заготовки', $name, round($quantity, 3), $unit];
            }
        }
        $stock = [['Дата', 'Позиция', 'Единица', 'Остаток', 'Цена, ₽', 'Мера цены', 'Стоимость, ₽']];
        foreach ($stockRows as $row) {
            $stock[] = [
                $row['date'], $row['item'], $row['unit'], $row['balance_milli'] / 1000,
                $row['price_kopecks'] === null ? 'Не задана' : $row['price_kopecks'] / 100,
                $row['measure_milli'] === null ? '' : number_format($row['measure_milli'] / (in_array($row['measure_unit'], ['kg', 'l'], true) ? 1000000 : 1000), 3, '.', '').' '.$row['measure_unit'],
                $row['value_kopecks'] === null ? 'Не задана' : $row['value_kopecks'] / 100,
            ];
        }
        $temporary = tempnam(sys_get_temp_dir(), 'bakery-period-');
        if ($temporary === false) {
            throw new RuntimeException('Не удалось создать отчёт.');
        }
        unlink($temporary);
        $path = $temporary.'.zip';
        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            $archive->addFromString('[Content_Types].xml', $this->contentTypes());
            $archive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $archive->addFromString('xl/workbook.xml', $this->workbook());
            $archive->addFromString('xl/_rels/workbook.xml.rels', $this->relationships());
            foreach ([$orders, $products, $ingredients, $stock] as $index => $rows) {
                $archive->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->sheet($rows));
            }
            unset($archive);

            return $path;
        } catch (\Throwable $error) {
            if (file_exists($path)) {
                unlink($path);
            }
            throw $error;
        }
    }

    private function contentTypes(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        for ($index = 1; $index <= 4; $index++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml.'</Types>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach (['Заказы', 'Изделия', 'Сырьё', 'Склад по дням'] as $index => $name) {
            $id = $index + 1;
            $sheets .= '<sheet name="'.$name.'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>';
    }

    private function relationships(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($index = 1; $index <= 4; $index++) {
            $xml .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }

        return $xml.'</Relationships>';
    }

    /** @param list<list<string|int|float>> $rows */
    private function sheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowIndex => $values) {
            $number = $rowIndex + 1;
            $xml .= '<row r="'.$number.'">';
            foreach ($values as $index => $value) {
                $ref = substr('ABCDEFGHIJKLMNOPQRSTUVWXYZ', $index, 1).$number;
                $xml .= is_string($value)
                    ? '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>'
                    : '<c r="'.$ref.'"><v>'.$value.'</v></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }
}
