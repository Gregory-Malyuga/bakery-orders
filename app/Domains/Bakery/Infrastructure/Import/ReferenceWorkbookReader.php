<?php

namespace Domains\Bakery\Infrastructure\Import;

use DOMDocument;
use DOMNode;
use DOMXPath;
use PharData;
use RuntimeException;
use SimpleXMLElement;
use XMLReader;

class ReferenceWorkbookReader
{
    /** @return array{stores:list<string>,products:list<string>,prices:array<int,array<int,int|null>>} */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Исходный XLSX не найден.');
        }
        $archive = new PharData($path);
        if (! isset($archive['xl/workbook.xml'], $archive['xl/sharedStrings.xml'], $archive['xl/worksheets/sheet8.xml'])) {
            throw new RuntimeException('В XLSX отсутствуют необходимые листы.');
        }
        if (! str_contains($archive['xl/workbook.xml']->getContent(), 'name="Лист1"')) {
            throw new RuntimeException('Не найден лист «Лист1» с прайсом.');
        }
        $shared = $this->sharedStrings($archive['xl/sharedStrings.xml']->getContent());
        $reader = new XMLReader;
        if (! $reader->open('phar://'.$path.'/xl/worksheets/sheet8.xml')) {
            throw new RuntimeException('Не удалось прочитать лист цен.');
        }
        $rows = [];
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $number = (int) $reader->getAttribute('r');
            if ($number > 24) {
                break;
            }
            $rows[$number] = $this->cells($reader->readOuterXML(), $shared);
        }
        $reader->close();
        $headers = $rows[1] ?? [];
        $stores = [];
        $storeKeys = [];
        for ($column = 2; $column <= 73; $column++) {
            $name = trim($headers[$column] ?? '');
            $key = $this->key($name);
            if ($name === '' || mb_strlen($name) > 255 || isset($storeKeys[$key])) {
                throw new RuntimeException("Пустое, слишком длинное или повторяющееся название магазина в колонке {$column}.");
            }
            $storeKeys[$key] = true;
            $stores[] = $name;
        }
        $products = [];
        $productKeys = [];
        $prices = [];
        for ($row = 2; $row <= 24; $row++) {
            $name = trim($rows[$row][1] ?? '');
            $key = $this->key($name);
            if ($name === '' || mb_strlen($name) > 255 || isset($productKeys[$key])) {
                throw new RuntimeException("Пустое, слишком длинное или повторяющееся название товара в строке {$row}.");
            }
            $productKeys[$key] = true;
            $products[] = $name;
            $priceRow = [];
            for ($column = 2; $column <= 73; $column++) {
                $raw = $rows[$row][$column] ?? '';
                if (! preg_match('/^\d{1,8}(?:\.\d+)?$/', $raw)) {
                    throw new RuntimeException("Нет цены или неверный формат в строке {$row}, колонке {$column}.");
                }
                [$rubles, $fraction] = array_pad(explode('.', $raw), 2, '');
                $digits = str_pad(substr($fraction, 0, 3), 3, '0');
                $price = (int) $rubles * 100 + (int) substr($digits, 0, 2) + ((int) $digits[2] >= 5 ? 1 : 0);
                $priceRow[] = $price === 0 ? null : $price;
            }
            $prices[] = $priceRow;
        }

        return ['stores' => $stores, 'products' => $products, 'prices' => $prices];
    }

    /** @return list<string> */
    private function sharedStrings(string $xml): array
    {
        $document = new DOMDocument;
        if (! $document->loadXML($xml)) {
            throw new RuntimeException('Не удалось прочитать текстовые значения XLSX.');
        }
        $xpath = new DOMXPath($document);
        $strings = [];
        foreach ($xpath->query('//*[local-name()="si"]') ?: [] as $item) {
            if (! $item instanceof DOMNode) {
                continue;
            }
            $value = '';
            foreach ($xpath->query('.//*[local-name()="t"]', $item) ?: [] as $part) {
                if ($part instanceof DOMNode) {
                    $value .= $part->textContent;
                }
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $shared
     * @return array<int, string>
     */
    private function cells(string $fragment, array $shared): array
    {
        $row = simplexml_load_string($fragment);
        if (! $row instanceof SimpleXMLElement) {
            throw new RuntimeException('Некорректная строка листа цен.');
        }
        $values = [];
        foreach ($row->xpath('//*[local-name()="c"]') ?: [] as $cell) {
            $reference = (string) $cell['r'];
            if (! preg_match('/^([A-Z]+)\d+$/', $reference, $match)) {
                continue;
            }
            $column = 0;
            foreach (str_split($match[1]) as $letter) {
                $column = $column * 26 + ord($letter) - 64;
            }
            $value = $cell->xpath('./*[local-name()="v"]');
            if (! is_array($value) || ! isset($value[0])) {
                continue;
            }
            $raw = (string) $value[0];
            $values[$column] = (string) $cell['t'] === 's' ? ($shared[(int) $raw] ?? '') : $raw;
        }

        return $values;
    }

    public function key(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)) ?? $name);
    }
}
