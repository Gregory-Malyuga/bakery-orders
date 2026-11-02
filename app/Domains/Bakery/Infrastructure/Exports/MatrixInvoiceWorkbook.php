<?php

namespace Domains\Bakery\Infrastructure\Exports;

use Domains\Bakery\Application\Exports\InvoiceWorkbookInterface;
use Domains\Bakery\Application\OrderReturnSummary;
use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Support\Carbon;
use Phar;
use PharData;
use RuntimeException;

/** @phpstan-import-type GroupInvoice from \Domains\Bakery\Application\GroupInvoiceService */
class MatrixInvoiceWorkbook implements InvoiceWorkbookInterface
{
    public function __construct(private readonly OrderReturnSummary $returnSummary) {}

    /**
     * @param  iterable<Order>  $orders
     * @param  iterable<Product>  $products
     * @param  list<GroupInvoice>  $groups
     */
    public function create(iterable $orders, iterable $products, array $groups = [], ?string $date = null, ?string $generatedAt = null): string
    {
        /** @var list<Order> $orderList */
        $orderList = iterator_to_array($orders, false);
        $returns = $this->returnSummary->forOrders($orderList);
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
            $groups = array_values(array_filter($groups, static fn (array $group): bool => $group['has_orders']));
            $invoiceCount = $orderList === [] && $groups !== [] ? 0 : max(1, count($orderList));
            $summaryCount = $invoiceCount > 0 ? 1 : 0;
            $sheetCount = $invoiceCount + count($groups) + $summaryCount;
            $archive->addFromString('[Content_Types].xml', $this->contentTypes($sheetCount));
            $archive->addFromString('_rels/.rels', $this->rootRelationships());
            $archive->addFromString('xl/workbook.xml', $this->workbook($orderList, $groups, $summaryCount > 0));
            $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships($sheetCount));
            $archive->addFromString('xl/styles.xml', $this->styles());
            foreach ($orderList as $index => $order) {
                $archive->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->invoiceSheet($order, $returns[$order->id] ?? ['quantities' => [], 'extras' => [], 'amount' => 0]));
            }
            if ($orderList === [] && $invoiceCount > 0) {
                $archive->addFromString('xl/worksheets/sheet1.xml', $this->invoiceSheet(null, ['quantities' => [], 'extras' => [], 'amount' => 0]));
            }
            foreach ($groups as $index => $group) {
                $archive->addFromString('xl/worksheets/sheet'.($invoiceCount + $index + 1).'.xml', $this->groupSheet($group, $date ?? '', $generatedAt ?? now('Europe/Moscow')->format('d.m.Y H:i')));
            }
            if ($summaryCount > 0) {
                $archive->addFromString('xl/worksheets/sheet'.$sheetCount.'.xml', $this->orderSheet($orderList, $catalog));
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

    /** @param array{quantities:array<int,int>,extras:list<array{product_id:int,name:string,quantity:int,unit_kopecks:int}>,amount:int} $returns */
    private function invoiceSheet(?Order $order, array $returns): string
    {
        $cells = [];
        if ($order === null) {
            $this->put($cells, 1, $this->label(1, 1, 'На выбранную дату принятых заказов нет.'));

            return $this->sheet($cells, '', false);
        }

        $hasReturns = $returns['quantities'] !== [] || $returns['extras'] !== [];
        $widths = [];
        foreach ($hasReturns ? [10, 43, 16, 16, 16, 17, 20] : [10, 43, 16, 16, 17] as $index => $width) {
            $column = $index + 1;
            $widths[] = '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }
        $this->put($cells, 1, $this->label(3, 1, 'Дата поставки'));
        $this->put($cells, 1, $this->label(4, 1, $order->delivery_date->format('d.m.Y')));
        $this->put($cells, 2, $this->blank(1, 2, 9));
        $this->put($cells, 2, $this->label(2, 2, 'Накладная №'.$order->id, 9));
        for ($column = 3; $column <= ($hasReturns ? 7 : 5); $column++) {
            $this->put($cells, 2, $this->blank($column, 2, 9));
        }
        if ($order->comment !== null && $order->comment !== '') {
            $this->put($cells, 3, $this->label(2, 3, 'Комментарий: '.$order->comment));
        }
        $this->put($cells, 4, $this->label(1, 4, 'Кому'));
        $this->put($cells, 4, $this->label(2, 4, $order->store_name, 1));
        $this->put($cells, 5, $this->label(2, 5, $order->store_address));
        $titles = $hasReturns
            ? ['№', 'Товар', 'Заказано', 'Возврат', 'Цена, ₽', 'Сумма, ₽', 'После возврата, ₽']
            : ['№', 'Товар', 'Заказано', 'Цена, ₽', 'Сумма, ₽'];
        foreach ($titles as $index => $title) {
            $this->put($cells, 6, $this->label($index + 1, 6, $title, 5));
        }

        $position = 0;
        foreach ($order->items as $item) {
            $row = 7 + $position++;
            $returned = $returns['quantities'][$item->product_id] ?? 0;
            $this->put($cells, $row, $this->number(1, $row, (string) $position, 4));
            $this->put($cells, $row, $this->label(2, $row, $item->product_name, 4));
            $this->put($cells, $row, $this->number(3, $row, (string) $item->quantity, 4));
            if ($hasReturns) {
                $this->put($cells, $row, $this->number(4, $row, (string) $returned, 4));
            }
            $priceColumn = $hasReturns ? 5 : 4;
            $sumColumn = $hasReturns ? 6 : 5;
            $this->put($cells, $row, $this->number($priceColumn, $row, $this->rubles($item->unit_kopecks), 6));
            $this->put($cells, $row, $this->formula($sumColumn, $row, $this->ref(3, $row).'*'.$this->ref($priceColumn, $row), $this->rubles($item->total_kopecks), 6));
            if ($hasReturns) {
                $this->put($cells, $row, $this->formula(7, $row, '('.$this->ref(3, $row).'-'.$this->ref(4, $row).')*'.$this->ref(5, $row), $this->rubles(($item->quantity - $returned) * $item->unit_kopecks), 6));
            }
        }
        foreach ($returns['extras'] as $extra) {
            $row = 7 + $position++;
            $this->put($cells, $row, $this->number(1, $row, (string) $position, 4));
            $this->put($cells, $row, $this->label(2, $row, $extra['name'], 4));
            $this->put($cells, $row, $this->number(3, $row, '0', 4));
            $this->put($cells, $row, $this->number(4, $row, (string) $extra['quantity'], 4));
            $this->put($cells, $row, $this->number(5, $row, $this->rubles($extra['unit_kopecks']), 6));
            $this->put($cells, $row, $this->formula(6, $row, 'C'.$row.'*E'.$row, '0.00', 6));
            $this->put($cells, $row, $this->formula(7, $row, '(C'.$row.'-D'.$row.')*E'.$row, $this->rubles(-$extra['quantity'] * $extra['unit_kopecks']), 6));
        }
        $lastRow = 6 + $position;
        $totalRow = $lastRow + 1;
        $this->put($cells, $totalRow, $this->blank(1, $totalRow, 7));
        $this->put($cells, $totalRow, $this->label(2, $totalRow, 'Итого', 7));
        $this->put($cells, $totalRow, $this->formula(3, $totalRow, "SUM(C7:C{$lastRow})", (string) $this->quantity($order), 7));
        if ($hasReturns) {
            $returnedQuantity = array_sum($returns['quantities']) + array_sum(array_column($returns['extras'], 'quantity'));
            $this->put($cells, $totalRow, $this->formula(4, $totalRow, "SUM(D7:D{$lastRow})", (string) $returnedQuantity, 7));
            $this->put($cells, $totalRow, $this->blank(5, $totalRow, 7));
            $this->put($cells, $totalRow, $this->formula(6, $totalRow, "SUM(F7:F{$lastRow})", $this->rubles($order->total_kopecks), 8));
            $this->put($cells, $totalRow, $this->formula(7, $totalRow, "SUM(G7:G{$lastRow})", $this->rubles($order->total_kopecks - $returns['amount']), 8));
        } else {
            $this->put($cells, $totalRow, $this->blank(4, $totalRow, 7));
            $this->put($cells, $totalRow, $this->formula(5, $totalRow, "SUM(E7:E{$lastRow})", $this->rubles($order->total_kopecks), 8));
        }

        return $this->sheet($cells, implode('', $widths), false);
    }

    /** @param GroupInvoice $group */
    private function groupSheet(array $group, string $date, string $generatedAt): string
    {
        $cells = [];
        $hasReturns = $group['show_return_columns'] && $group['returned_quantity'] > 0;
        $widths = [];
        foreach ($hasReturns ? [10, 43, 16, 16, 16, 17, 20] : [10, 43, 16, 16, 17] as $index => $width) {
            $column = $index + 1;
            $widths[] = '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }
        $this->put($cells, 1, $this->label(1, 1, 'Дата поставки: '.Carbon::parse($date)->format('d.m.Y')));
        $this->put($cells, 2, $this->label(1, 2, 'Сводная накладная группы «'.$group['name'].'»', 9));
        $this->put($cells, 3, $this->label(1, 3, 'Сформировано: '.$generatedAt.' МСК'));
        $this->put($cells, 4, $this->label(1, 4, 'Не прибавлять к общему итогу — заказы уже учтены', 7));
        $titles = $hasReturns
            ? ['№', 'Товар', 'Заказано', 'Возврат', 'Цена, ₽', 'Сумма, ₽', 'После возврата, ₽']
            : ['№', 'Товар', 'Заказано', 'Цена, ₽', 'Сумма, ₽'];
        foreach ($titles as $index => $title) {
            $this->put($cells, 6, $this->label($index + 1, 6, $title, 5));
        }
        foreach ($group['products'] as $index => $product) {
            $row = 7 + $index;
            $this->put($cells, $row, $this->number(1, $row, (string) ($index + 1), 4));
            $this->put($cells, $row, $this->label(2, $row, $product['name'], 4));
            $this->put($cells, $row, $this->number(3, $row, (string) $product['quantity'], 4));
            if ($hasReturns) {
                $this->put($cells, $row, $this->number(4, $row, (string) $product['returned'], 4));
            }
            $this->put($cells, $row, $this->number($hasReturns ? 5 : 4, $row, $this->rubles($product['unit_kopecks']), 6));
            $this->put($cells, $row, $this->number($hasReturns ? 6 : 5, $row, $this->rubles($group['show_return_columns'] ? $product['gross'] : $product['net']), 6));
            if ($hasReturns) {
                $this->put($cells, $row, $this->number(7, $row, $this->rubles($product['net']), 6));
            }
        }
        $totalRow = 7 + count($group['products']);
        $this->put($cells, $totalRow, $this->blank(1, $totalRow, 7));
        $this->put($cells, $totalRow, $this->label(2, $totalRow, 'Итого', 7));
        $this->put($cells, $totalRow, $this->number(3, $totalRow, (string) $group['quantity'], 7));
        if ($hasReturns) {
            $this->put($cells, $totalRow, $this->number(4, $totalRow, (string) $group['returned_quantity'], 7));
            $this->put($cells, $totalRow, $this->blank(5, $totalRow, 7));
            $this->put($cells, $totalRow, $this->number(6, $totalRow, $this->rubles($group['gross']), 8));
            $this->put($cells, $totalRow, $this->number(7, $totalRow, $this->rubles($group['net']), 8));
        } else {
            $this->put($cells, $totalRow, $this->blank(4, $totalRow, 7));
            $this->put($cells, $totalRow, $this->number(5, $totalRow, $this->rubles($group['show_return_columns'] ? $group['gross'] : $group['net']), 8));
        }

        $memberTitleRow = $totalRow + 2;
        $this->put($cells, $memberTitleRow, $this->label(2, $memberTitleRow, 'Состав группы на момент формирования', 9));
        $memberHeaderRow = $memberTitleRow + 1;
        $memberHasReturns = $group['show_return_columns'];
        $memberHeaders = $memberHasReturns
            ? ['№', 'Торговая точка', 'Изделий, шт.', 'Заказ, ₽', 'Возврат, ₽', 'К оплате, ₽']
            : ['№', 'Торговая точка', 'Изделий, шт.', 'Заказ, ₽', 'К оплате, ₽'];
        // No independent return columns for Baltic/Kolpino; only the payable net amount.
        foreach ($memberHeaders as $index => $heading) {
            $this->put($cells, $memberHeaderRow, $this->label($index + 1, $memberHeaderRow, $heading, 5));
        }
        foreach ($group['members'] as $index => $member) {
            $row = $memberHeaderRow + $index + 1;
            $label = $member['name'].' · '.$member['address'].($member['ordered'] ? '' : ' · нет заказа');
            $this->put($cells, $row, $this->number(1, $row, (string) ($index + 1), 4));
            $this->put($cells, $row, $this->label(2, $row, $label, 4));
            $this->put($cells, $row, $this->number(3, $row, (string) $member['quantity'], 4));
            if ($memberHasReturns) {
                $this->put($cells, $row, $this->number(4, $row, $this->rubles($member['gross']), 6));
                $this->put($cells, $row, $this->number(5, $row, $this->rubles($member['returns']), 6));
                $this->put($cells, $row, $this->number(6, $row, $this->rubles($member['net']), 6));
            } else {
                $this->put($cells, $row, $this->number(4, $row, $this->rubles($member['gross']), 6));
                $this->put($cells, $row, $this->number(5, $row, $this->rubles($member['net']), 6));
            }
        }
        $memberTotalRow = $memberHeaderRow + count($group['members']) + 1;
        $this->put($cells, $memberTotalRow, $this->blank(1, $memberTotalRow, 7));
        $this->put($cells, $memberTotalRow, $this->label(2, $memberTotalRow, 'Итого по точкам', 7));
        $this->put($cells, $memberTotalRow, $this->number(3, $memberTotalRow, (string) $group['quantity'], 7));
        if ($memberHasReturns) {
            $this->put($cells, $memberTotalRow, $this->number(4, $memberTotalRow, $this->rubles($group['gross']), 8));
            $this->put($cells, $memberTotalRow, $this->number(5, $memberTotalRow, $this->rubles($group['returns']), 8));
            $this->put($cells, $memberTotalRow, $this->number(6, $memberTotalRow, $this->rubles($group['net']), 8));
        } else {
            $this->put($cells, $memberTotalRow, $this->number(4, $memberTotalRow, $this->rubles($group['gross']), 8));
            $this->put($cells, $memberTotalRow, $this->number(5, $memberTotalRow, $this->rubles($group['net']), 8));
        }
        $commentRow = $memberTotalRow + 2;
        foreach ($group['members'] as $member) {
            if ($member['comment']) {
                $this->put($cells, $commentRow, $this->label(2, $commentRow, 'Комментарий · '.$member['name'].': '.$member['comment']));
                $commentRow++;
            }
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
        $this->put($cells, 1, $this->label(1, 1, 'Заказ на '.$date, 9));
        $this->put($cells, 3, $this->label(1, 3, 'Продукция', 5));
        foreach ($orders as $index => $order) {
            $column = $index + 2;
            $widths[] = '<col min="'.$column.'" max="'.$column.'" width="24" customWidth="1"/>';
            $this->put($cells, 1, $this->blank($column, 1, 9));
            $this->put($cells, 3, $this->label($column, 3, $order->store_name, 5));
        }
        $totalColumn = count($orders) + 2;
        $widths[] = '<col min="'.$totalColumn.'" max="'.$totalColumn.'" width="18" customWidth="1"/>';
        $this->put($cells, 1, $this->blank($totalColumn, 1, 9));
        $this->put($cells, 3, $this->label($totalColumn, 3, 'Итого, шт.', 5));
        $position = 0;
        foreach ($catalog as $productId => $name) {
            $row = 4 + $position++;
            $this->put($cells, $row, $this->label(1, $row, $name, 4));
            $total = 0;
            foreach ($orders as $index => $order) {
                $quantity = $this->productQuantity($order, $productId);
                $total += $quantity;
                $this->put($cells, $row, $this->number($index + 2, $row, (string) $quantity, 4));
            }
            $this->put($cells, $row, $orders === []
                ? $this->number($totalColumn, $row, '0', 4)
                : $this->formula($totalColumn, $row, 'SUM(B'.$row.':'.$this->ref($totalColumn - 1, $row).')', (string) $total, 4));
        }
        $quantityRow = 4 + count($catalog);
        $moneyRow = $quantityRow + 1;
        $this->put($cells, $quantityRow, $this->label(1, $quantityRow, 'Всего, шт.', 7));
        $this->put($cells, $moneyRow, $this->label(1, $moneyRow, 'Итого, ₽', 7));
        foreach ($orders as $index => $order) {
            $column = $index + 2;
            $letter = $this->column($column);
            $this->put($cells, $quantityRow, $this->formula($column, $quantityRow, "SUM({$letter}4:{$letter}".($quantityRow - 1).')', (string) $this->quantity($order), 7));
            $this->put($cells, $moneyRow, $this->number($column, $moneyRow, $this->rubles($order->total_kopecks), 8));
        }
        $totalQuantity = array_sum(array_map(fn (Order $order): int => $this->quantity($order), $orders));
        $totalMoney = array_sum(array_map(fn (Order $order): int => $order->total_kopecks, $orders));
        $this->put($cells, $quantityRow, $orders === []
            ? $this->number($totalColumn, $quantityRow, '0', 7)
            : $this->formula($totalColumn, $quantityRow, 'SUM('.$this->column($totalColumn).'4:'.$this->ref($totalColumn, $quantityRow - 1).')', (string) $totalQuantity, 7));
        $this->put($cells, $moneyRow, $orders === []
            ? $this->number($totalColumn, $moneyRow, '0.00', 8)
            : $this->formula($totalColumn, $moneyRow, 'SUM(B'.$moneyRow.':'.$this->ref($totalColumn - 1, $moneyRow).')', $this->rubles($totalMoney), 8));

        return $this->sheet($cells, implode('', $widths), true);
    }

    /** @param array<int, list<string>> $cells */
    private function sheet(array $cells, string $widths, bool $freeze): string
    {
        ksort($cells);
        $rows = '';
        foreach ($cells as $number => $content) {
            $height = $freeze ? ($number === 1 ? 26 : ($number === 3 ? 36 : 0)) : ($number === 2 ? 26 : ($number === 6 ? 32 : 0));
            $rows .= '<row r="'.$number.'"'.($height ? ' ht="'.$height.'" customHeight="1"' : '').'>'.implode('', $content).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .($freeze ? '' : '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>')
            .'<sheetViews><sheetView showGridLines="0" workbookViewId="0">'.($freeze ? '<pane xSplit="1" ySplit="3" topLeftCell="B4" activePane="bottomRight" state="frozen"/>' : '').'</sheetView></sheetViews>'
            .'<cols>'.$widths.'</cols><sheetData>'.$rows.'</sheetData>'
            .($freeze ? '' : '<pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="1"/>')
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

    private function blank(int $column, int $row, int $style): string
    {
        return '<c r="'.$this->ref($column, $row).'" s="'.$style.'"/>';
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
        $absolute = abs($kopecks);

        return ($kopecks < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
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

    private function contentTypes(int $sheetCount): string
    {
        $sheets = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$sheets
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

    /** @param list<Order> $orders
     * @param  list<GroupInvoice>  $groups
     */
    private function workbook(array $orders, array $groups, bool $includeSummary): string
    {
        $sheets = '';
        $usedNames = ['заказ' => true];
        foreach ($orders as $index => $order) {
            $duplicate = 1;
            do {
                $suffix = $duplicate === 1 ? '' : ' ('.$duplicate.')';
                $name = $this->invoiceSheetName($order, 31 - mb_strlen($suffix)).$suffix;
                $duplicate++;
            } while (isset($usedNames[mb_strtolower($name)]));
            $usedNames[mb_strtolower($name)] = true;
            $number = $index + 1;
            $sheets .= '<sheet name="'.htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
        }
        if ($orders === [] && $includeSummary) {
            $sheets = '<sheet name="Накладная" sheetId="1" r:id="rId1"/>';
        }
        $nextSheet = $orders === [] && $includeSummary ? 2 : count($orders) + 1;
        foreach ($groups as $group) {
            $name = mb_substr($this->safeSheetPart('Группа '.$group['name']), 0, 31);
            $duplicate = 1;
            $base = $name;
            while (isset($usedNames[mb_strtolower($name)])) {
                $duplicate++;
                $suffix = ' ('.$duplicate.')';
                $name = mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix;
            }
            $usedNames[mb_strtolower($name)] = true;
            $sheets .= '<sheet name="'.htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="'.$nextSheet.'" r:id="rId'.$nextSheet.'"/>';
            $nextSheet++;
        }
        if ($includeSummary) {
            $sheets .= '<sheet name="Заказ" sheetId="'.$nextSheet.'" r:id="rId'.$nextSheet.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'<calcPr calcId="0" fullCalcOnLoad="1"/></workbook>';
    }

    private function invoiceSheetName(Order $order, int $maxLength): string
    {
        $address = $this->safeSheetPart($order->store_address);
        $customer = $this->safeSheetPart($order->store_name);
        if ($address === '' || $customer === '') {
            return mb_substr($address ?: ($customer ?: 'Накладная '.$order->id), 0, $maxLength);
        }

        $room = $maxLength - 3; // " — "
        $addressLength = min(mb_strlen($address), intdiv($room, 2));
        $customerLength = min(mb_strlen($customer), $room - $addressLength);
        $addressLength = min(mb_strlen($address), $room - $customerLength);

        return mb_substr($address, 0, $addressLength).' — '.mb_substr($customer, 0, $customerLength);
    }

    private function safeSheetPart(string $value): string
    {
        $value = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $value);
        $value = preg_replace('/[\x00-\x1F\s]+/u', ' ', $value) ?? $value;

        return trim($value, " '");
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $sheets = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $sheets .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$sheets
            .'<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font><font><b/><sz val="13"/><name val="Arial"/></font></fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFFF00"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE2F0D9"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FF727D72"/></left><right style="thin"><color rgb="FF727D72"/></right>'
            .'<top style="thin"><color rgb="FF727D72"/></top><bottom style="thin"><color rgb="FF727D72"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="10"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="4" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
