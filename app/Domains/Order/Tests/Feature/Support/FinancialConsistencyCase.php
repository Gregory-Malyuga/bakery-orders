<?php

namespace Domains\Order\Tests\Feature\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class FinancialConsistencyCase extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<int, array<string, string>> */
    protected function downloadCells(string $url, int $sheetCount): array
    {
        $response = $this->get($url)->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $sheets = [];
        for ($index = 1; $index <= $sheetCount; $index++) {
            $xml = simplexml_load_string($archive['xl/worksheets/sheet'.$index.'.xml']->getContent());
            $this->assertNotFalse($xml);
            $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = [];
            $nodes = $xml->xpath('//x:sheetData/x:row/x:c');
            if (! is_array($nodes)) {
                $this->fail('Не удалось прочитать ячейки листа '.$index);
            }
            foreach ($nodes as $cell) {
                $cells[(string) $cell['r']] = isset($cell->is->t) ? (string) $cell->is->t : (string) $cell->v;
                if (isset($cell->f)) {
                    $cells['='.$cell['r']] = (string) $cell->f;
                }
            }
            $sheets[$index] = $cells;
        }
        unset($archive);
        unlink($path);

        return $sheets;
    }

    /** @param array<string, string> $cells */
    protected function cellForProduct(array $cells, string $product, string $valueColumn, string $nameColumn = 'B'): string
    {
        foreach ($cells as $ref => $value) {
            if (preg_match('/^'.preg_quote($nameColumn, '/').'([0-9]+)$/', $ref, $match) && $value === $product) {
                return $cells[$valueColumn.$match[1]] ?? '';
            }
        }

        $this->fail('Нет строки '.$product);
    }

    /** @param array<string, string> $cells */
    protected function formulaForProduct(array $cells, string $product, string $column): string
    {
        foreach ($cells as $ref => $value) {
            if (preg_match('/^B([0-9]+)$/', $ref, $match) && $value === $product) {
                return $cells['='.$column.$match[1]] ?? '';
            }
        }

        $this->fail('Нет формулы для '.$product);
    }

    /** @param array<string, string> $cells */
    protected function reportValue(array $cells, string $store, string $product, string $column): string
    {
        foreach ($cells as $ref => $value) {
            if (preg_match('/^B([0-9]+)$/', $ref, $match) && $value === $store && ($cells['C'.$match[1]] ?? null) === $product) {
                return $cells[$column.$match[1]] ?? '';
            }
        }

        $this->fail('Нет строки '.$store.' / '.$product);
    }
}
