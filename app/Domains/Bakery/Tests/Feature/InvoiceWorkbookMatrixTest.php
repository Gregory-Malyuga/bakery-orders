<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Infrastructure\Exports\MatrixInvoiceWorkbook;
use Illuminate\Foundation\Testing\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PharData;

/** @phpstan-import-type GroupInvoice from \Domains\Bakery\Application\GroupInvoiceService */
class InvoiceWorkbookMatrixTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function groupNames(): iterable
    {
        $names = [
            'Обычная группа',
            'Короткая',
            'Группа с пробелами',
            'Балтийская общая',
            'Колпино',
            'Тест/слеш',
            'Тест\обратный',
            'Тест:двоеточие',
            'Тест?вопрос',
            'Тест*звёздочка',
            'Тест[скобки]',
            'Тест]скобка',
            'Кавычки "двойные"',
            "Кавычки 'одинарные'",
            'Амперсанд & знак',
            'Меньше < больше >',
            'Юникод 🌿',
            'Длинное имя группы для проверки ограничения в тридцать один символ',
            '12345',
            'Группа-А',
            'Группа_Б',
            'Группа.В',
            'Группа №7',
            'Точка и запятая; тест',
            'Много  пробелов',
        ];
        foreach ($names as $index => $name) {
            yield 'sheet-name-'.($index + 1) => [$name];
        }
    }

    /**
     * @return iterable<string, array{bool, int, string}>
     */
    public static function returnLayouts(): iterable
    {
        $names = ['Обычная', 'Балтийская', 'Колпино', 'Сводная', 'Проверка'];
        $quantities = [0, 1, 2, 3, 4];
        foreach ($names as $name) {
            foreach ($quantities as $returned) {
                yield $name.'-returns-'.$returned => [
                    $name !== 'Балтийская' && $name !== 'Колпино',
                    $returned,
                    $name,
                ];
            }
        }
    }

    /** @return GroupInvoice */
    private function group(string $name, bool $showReturns, int $returned): array
    {
        $gross = 5000;
        $returns = $returned * 1000;

        return [
            'id' => 1,
            'name' => $name,
            'members' => [[
                'name' => 'Точка', 'address' => 'Адрес', 'comment' => null,
                'ordered' => true, 'quantity' => 5, 'gross' => $gross,
                'returns' => $returns, 'net' => $gross - $returns,
            ]],
            'products' => [[
                'name' => 'Булочка', 'quantity' => 5, 'returned' => $returned,
                'unit_kopecks' => 1000, 'gross' => $gross, 'net' => $gross - $returns,
            ]],
            'quantity' => 5,
            'returned_quantity' => $returned,
            'gross' => $gross,
            'returns' => $returns,
            'net' => $gross - $returns,
            'has_orders' => true,
            'show_return_columns' => $showReturns,
        ];
    }

    /** @param GroupInvoice $group
     *  @return array{string, string}
     */
    private function workbookParts(array $group): array
    {
        $path = app(MatrixInvoiceWorkbook::class)->create([], [], [$group], '2026-11-02', '02.11.2026 12:00');

        try {
            $archive = new PharData($path);
            $workbook = $archive['xl/workbook.xml']->getContent();
            $sheet = $archive['xl/worksheets/sheet1.xml']->getContent();
            unset($archive);

            return [$workbook, $sheet];
        } finally {
            @unlink($path);
        }
    }

    #[DataProvider('groupNames')]
    public function test_group_sheet_name_is_excel_safe_and_xml_valid(string $name): void
    {
        [$workbook, $sheet] = $this->workbookParts($this->group($name, true, 0));
        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($workbook));
        $this->assertNotFalse($doc->loadXML($sheet));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $nodes = $xpath->query('//x:sheet');
        $this->assertNotFalse($nodes);
        $this->assertCount(1, $nodes);
        $sheetName = $nodes->item(0)?->attributes?->getNamedItem('name')?->nodeValue;
        $this->assertNotNull($sheetName);
        $this->assertLessThanOrEqual(31, mb_strlen($sheetName));
        $this->assertSame(0, preg_match('~[\\\\/?:*\[\]]~u', $sheetName));
        $this->assertStringContainsString('Булочка', $sheet);
    }

    #[DataProvider('returnLayouts')]
    public function test_return_columns_follow_business_rule_in_both_group_sections(bool $showReturns, int $returned, string $name): void
    {
        [, $sheet] = $this->workbookParts($this->group($name, $showReturns, $returned));
        $expected = $showReturns && $returned > 0;

        $this->assertSame($expected, str_contains($sheet, '>Возврат</t>'));
        $this->assertSame($expected, str_contains($sheet, '>После возврата, ₽</t>'));
        $this->assertSame($expected, str_contains($sheet, '>Возврат, ₽</t>'));
        $this->assertStringContainsString('Итого по точкам', $sheet);
        $this->assertStringContainsString('К оплате, ₽', $sheet);
        $this->assertStringContainsString(number_format(50 - $returned * 10, 2, '.', ''), $sheet);
    }
}
