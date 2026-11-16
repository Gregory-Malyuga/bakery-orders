<?php

namespace Domains\Bakery\Infrastructure\Exports;

use Domains\Bakery\Application\Exports\InvoiceWorkbookInterface;
use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\Product;
use Phar;
use PharData;
use RuntimeException;

class MatrixInvoiceWorkbook implements InvoiceWorkbookInterface
{
    /**
     * @param  iterable<Order>  $orders
     * @param  iterable<Product>  $products
     */
    public function create(iterable $orders, iterable $products): string
    {
        /** @var list<Order> $orderList */
        $orderList = iterator_to_array($orders, false);
        $catalog = [];
        foreach ($products as $product) {
            if ($product->is_active) {
                $catalog[$product->id] = $product->name;
            }
        }
        foreach ($orderList as $order) {
            foreach ($order->items as $item) {
                $catalog[$item->product_id] ??= $item->product_name;
            }
        }

        $temporary = tempnam(sys_get_temp_dir(), 'bakery-invoices-');
        if ($temporary === false) {
            throw new RuntimeException('Не удалось создать временный файл накладной.');
        }
        unlink($temporary);
        $path = $temporary.'.zip';

        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            $archive->addFromString('[Content_Types].xml', $this->contentTypes());
            $archive->addFromString('_rels/.rels', $this->rootRelationships());
            $archive->addFromString('xl/workbook.xml', $this->workbook());
            $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
            $archive->addFromString('xl/styles.xml', $this->styles());
            $archive->addFromString('xl/worksheets/sheet1.xml', $this->invoiceSheet($orderList, $catalog));
            $archive->addFromString('xl/worksheets/sheet2.xml', $this->orderSheet($orderList, $catalog));
            unset($archive);

            return $path;
        } catch (\Throwable $error) {
            if (file_exists($path)) {
                unlink($path);
            }
            throw $error;
        }
    }

    /**
     * @param  list<Order>  $orders
     * @param  array<int, string>  $catalog
     */
    private function invoiceSheet(array $orders, array $catalog): string
    {
        $cells = [];
        $widths = [];
        $lastProductRow = 6 + count($catalog);
        foreach ($orders as $index => $order) {
            $start = 1 + $index * 6;
            foreach ([10, 43, 16, 16, 17, 3] as $offset => $width) {
                $column = $start + $offset;
                $widths[] = '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
            }
            $this->put($cells, 1, $this->label($start + 3, 1, 'Дата поставки'));
            $this->put($cells, 1, $this->label($start + 4, 1, $order->delivery_date->format('d.m.Y')));
            $this->put($cells, 2, $this->label($start + 1, 2, 'Накладная №'.$order->id, 1));
            $this->put($cells, 4, $this->label($start, 4, 'Кому'));
            $this->put($cells, 4, $this->label($start + 1, 4, $order->store_name, 1));
            $this->put($cells, 5, $this->label($start + 1, 5, $order->store_address));
            foreach (['№', 'Товар', 'Кол-во', 'Цена, ₽', 'Сумма, ₽'] as $offset => $title) {
                $this->put($cells, 6, $this->label($start + $offset, 6, $title, 2));
            }
            $items = $order->items->keyBy('product_id');
            $position = 1;
            foreach ($catalog as $productId => $name) {
                $row = 6 + $position;
                $item = $items->get($productId);
                $this->put($cells, $row, $this->number($start, $row, (string) $position));
                $this->put($cells, $row, $this->label($start + 1, $row, $item === null ? $name : $item->product_name));
                $this->put($cells, $row, $this->number($start + 2, $row, (string) ($item === null ? 0 : $item->quantity)));
                if ($item !== null) {
                    $this->put($cells, $row, $this->number($start + 3, $row, $this->rubles($item->unit_kopecks), 3));
                    $this->put($cells, $row, $this->formula($start + 4, $row, $this->ref($start + 2, $row).'*'.$this->ref($start + 3, $row), $this->rubles($item->total_kopecks), 3));
                }
                $position++;
            }
            $totalRow = $lastProductRow + 1;
            $quantityColumn = $this->column($start + 2);
            $sumColumn = $this->column($start + 4);
            $this->put($cells, $totalRow, $this->label($start + 1, $totalRow, 'Итого', 2));
            $this->put($cells, $totalRow, $this->formula($start + 2, $totalRow, "SUM({$quantityColumn}7:{$quantityColumn}{$lastProductRow})", (string) $this->quantity($order)));
            $this->put($cells, $totalRow, $this->formula($start + 4, $totalRow, "SUM({$sumColumn}7:{$sumColumn}{$lastProductRow})", $this->rubles($order->total_kopecks), 3));
        }
        if ($orders === []) {
            $this->put($cells, 1, $this->label(1, 1, 'На выбранную дату принятых заказов нет.'));
        }

        return $this->sheet($cells, implode('', $widths), false);
    }

    /**
     * @param  list<Order>  $orders
     * @param  array<int, string>  $catalog
     */
    private function orderSheet(array $orders, array $catalog): string
    {
        $cells = [];
        $widths = ['<col min="1" max="1" width="43" customWidth="1"/>'];
        $date = $orders !== [] ? $orders[0]->delivery_date->format('d.m.Y') : '';
        $this->put($cells, 1, $this->label(1, 1, 'Заказ на '.$date, 1));
        $this->put($cells, 3, $this->label(1, 3, 'Продукция', 2));
        foreach ($orders as $index => $order) {
            $column = $index + 2;
            $widths[] = '<col min="'.$column.'" max="'.$column.'" width="24" customWidth="1"/>';
            $this->put($cells, 3, $this->label($column, 3, $order->store_name, 2));
        }
        $totalColumn = count($orders) + 2;
        $widths[] = '<col min="'.$totalColumn.'" max="'.$totalColumn.'" width="18" customWidth="1"/>';
        $this->put($cells, 3, $this->label($totalColumn, 3, 'Итого, шт.', 2));
        $position = 0;
        foreach ($catalog as $productId => $name) {
            $row = 4 + $position++;
            $this->put($cells, $row, $this->label(1, $row, $name));
            $total = 0;
            foreach ($orders as $index => $order) {
                $quantity = $this->productQuantity($order, $productId);
                $total += $quantity;
                $this->put($cells, $row, $this->number($index + 2, $row, (string) $quantity));
            }
            $this->put($cells, $row, $orders === []
                ? $this->number($totalColumn, $row, '0')
                : $this->formula($totalColumn, $row, 'SUM(B'.$row.':'.$this->ref($totalColumn - 1, $row).')', (string) $total));
        }
        $quantityRow = 4 + count($catalog);
        $moneyRow = $quantityRow + 1;
        $this->put($cells, $quantityRow, $this->label(1, $quantityRow, 'Всего, шт.', 2));
        $this->put($cells, $moneyRow, $this->label(1, $moneyRow, 'Итого, ₽', 2));
        foreach ($orders as $index => $order) {
            $column = $index + 2;
            $letter = $this->column($column);
            $this->put($cells, $quantityRow, $this->formula($column, $quantityRow, "SUM({$letter}4:{$letter}".($quantityRow - 1).')', (string) $this->quantity($order)));
            $this->put($cells, $moneyRow, $this->number($column, $moneyRow, $this->rubles($order->total_kopecks), 3));
        }
        $totalQuantity = array_sum(array_map(fn (Order $order): int => $this->quantity($order), $orders));
        $totalMoney = array_sum(array_map(fn (Order $order): int => $order->total_kopecks, $orders));
        $this->put($cells, $quantityRow, $orders === []
            ? $this->number($totalColumn, $quantityRow, '0')
            : $this->formula($totalColumn, $quantityRow, 'SUM('.$this->column($totalColumn).'4:'.$this->ref($totalColumn, $quantityRow - 1).')', (string) $totalQuantity));
        $this->put($cells, $moneyRow, $orders === []
            ? $this->number($totalColumn, $moneyRow, '0.00', 3)
            : $this->formula($totalColumn, $moneyRow, 'SUM(B'.$moneyRow.':'.$this->ref($totalColumn - 1, $moneyRow).')', $this->rubles($totalMoney), 3));

        return $this->sheet($cells, implode('', $widths), true);
    }

    /** @param array<int, list<string>> $cells */
    private function sheet(array $cells, string $widths, bool $freeze): string
    {
        ksort($cells);
        $rows = '';
        foreach ($cells as $number => $content) {
            $rows .= '<row r="'.$number.'">'.implode('', $content).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView showGridLines="0" workbookViewId="0">'.($freeze ? '<pane xSplit="1" ySplit="3" topLeftCell="B4" activePane="bottomRight" state="frozen"/>' : '').'</sheetView></sheetViews>'
            .'<cols>'.$widths.'</cols><sheetData>'.$rows.'</sheetData>'
            .'<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'</worksheet>';
    }

    /** @param array<int, list<string>> $cells */
    private function put(array &$cells, int $row, string $cell): void
    {
        $cells[$row][] = $cell;
    }

    private function label(int $column, int $row, string $value, int $style = 0): string
    {
        return '<c r="'.$this->ref($column, $row).'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
    }

    private function number(int $column, int $row, string $value, int $style = 0): string
    {
        return '<c r="'.$this->ref($column, $row).'" s="'.$style.'"><v>'.$value.'</v></c>';
    }

    private function formula(int $column, int $row, string $expression, string $cached, int $style = 0): string
    {
        return '<c r="'.$this->ref($column, $row).'" s="'.$style.'"><f>'.$expression.'</f><v>'.$cached.'</v></c>';
    }

    private function ref(int $column, int $row): string
    {
        return $this->column($column).$row;
    }

    private function column(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + $number % 26).$letters;
            $number = intdiv($number, 26);
        }

        return $letters;
    }

    private function rubles(int $kopecks): string
    {
        return intdiv($kopecks, 100).'.'.str_pad((string) ($kopecks % 100), 2, '0', STR_PAD_LEFT);
    }

    private function quantity(Order $order): int
    {
        $total = 0;
        foreach ($order->items as $item) {
            $total += $item->quantity;
        }

        return $total;
    }

    private function productQuantity(Order $order, int $productId): int
    {
        foreach ($order->items as $item) {
            if ($item->product_id === $productId) {
                return $item->quantity;
            }
        }

        return 0;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Накладная" sheetId="1" r:id="rId1"/><sheet name="Заказ" sheetId="2" r:id="rId2"/></sheets>'
            .'<calcPr calcId="0" fullCalcOnLoad="1"/></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
