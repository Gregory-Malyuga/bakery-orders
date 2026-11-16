<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $data = $this->analytics->build($filters['from'], $filters['to'], $filters['store'], $filters['product']);

        return view('bakery.analytics', [
            'filters' => $filters, 'data' => $data,
            'stores' => DB::table('bakeries')->orderBy('name')->get(['id', 'name']),
            'products' => DB::table('bakery_products')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $data = $this->analytics->build($filters['from'], $filters['to'], $filters['store'], $filters['product']);
        $filename = 'analitika-'.$filters['from'].'-'.$filters['to'].'.csv';

        return response()->streamDownload(static function () use ($data): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Раздел', 'Название', 'Заказы', 'Заказано, шт.', 'Возвращено, шт.', 'Выручка до возвратов, коп.', 'Возвраты, коп.', 'Выручка после возвратов, коп.', 'Доля возвратов, %'], ';');
            foreach (['summary' => 'Итого', 'days' => 'День', 'stores' => 'Точка', 'products' => 'Товар'] as $key => $section) {
                $rows = $key === 'summary' ? [$data['summary']] : $data[$key];
                foreach ($rows as $row) {
                    fputcsv($output, [$section, self::csvText($row['label']), $row['orders'], $row['pieces'], $row['returned_pieces'], $row['gross'], $row['returns'], $row['net'], number_format($row['return_share'], 2, '.', '')], ';');
                }
            }
            foreach ($data['missing_stores'] as $name) {
                fputcsv($output, ['Без заказа', self::csvText($name)], ';');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function csvText(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    /** @return array{from:string,to:string,store:int|null,product:int|null} */
    private function filters(Request $request): array
    {
        /** @var array{from?:string,to?:string,store?:int|string,product?:int|string} $input */
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'store' => ['nullable', 'integer', 'exists:bakeries,id'],
            'product' => ['nullable', 'integer', 'exists:bakery_products,id'],
        ]);
        $today = now('Europe/Moscow')->toDateString();
        $from = $input['from'] ?? now('Europe/Moscow')->subDays(29)->toDateString();
        $to = $input['to'] ?? $today;
        if ($from > $to) {
            abort(422, 'Начало периода позже конца.');
        }

        return ['from' => $from, 'to' => $to, 'store' => isset($input['store']) ? (int) $input['store'] : null, 'product' => isset($input['product']) ? (int) $input['product'] : null];
    }
}
