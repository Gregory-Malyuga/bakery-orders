<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\Exports\InvoiceWorkbookInterface;
use Domains\Bakery\Application\GroupInvoiceService;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @phpstan-import-type GroupInvoice from \Domains\Bakery\Application\GroupInvoiceService */
class StoreGroupController
{
    public function __construct(private readonly OrderingRepositoryInterface $orders, private readonly GroupInvoiceService $invoices, private readonly InvoiceWorkbookInterface $workbook) {}

    private function date(Request $request): string
    {
        /** @var array{date?:string} $data */
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return $data['date'] ?? now('Europe/Moscow')->addDay()->toDateString();
    }

    /** @return GroupInvoice */
    private function invoice(int $groupId, string $date): array
    {
        foreach ($this->invoices->forOrders($this->orders->orders($date)) as $group) {
            if ($group['id'] === $groupId && $group['has_orders']) {
                return $group;
            }
        }
        abort(404);
    }

    public function index(): View
    {
        $groups = DB::table('bakery_store_groups')->orderBy('name')->get();
        $members = DB::table('bakery_store_group_members')->get()->groupBy('group_id');

        return view('bakery.groups', [
            'groups' => $groups,
            'members' => $members,
            'stores' => $this->orders->stores()->sortBy('name'),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $name = $request->input('name');
        if (is_string($name)) {
            $request->merge(['name' => trim($name)]);
        }
        /** @var array{id?:int|string,name:string,show_in_invoice?:bool,exclude_from_labels?:bool,stores?:list<int|string>} $data */
        $data = $request->validate([
            'id' => ['nullable', 'integer', Rule::exists('bakery_store_groups', 'id')],
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u', Rule::unique('bakery_store_groups', 'name')->ignore($request->integer('id'))],
            'show_in_invoice' => ['sometimes', 'boolean'],
            'exclude_from_labels' => ['sometimes', 'boolean'],
            'stores' => ['sometimes', 'array'],
            'stores.*' => ['integer', 'distinct', Rule::exists('bakeries', 'id')],
        ]);

        DB::transaction(static function () use ($data): void {
            $values = [
                'name' => trim($data['name']),
                'show_in_invoice' => (bool) ($data['show_in_invoice'] ?? false),
                'exclude_from_labels' => (bool) ($data['exclude_from_labels'] ?? false),
                'updated_at' => now(),
            ];
            if (isset($data['id'])) {
                $id = (int) $data['id'];
                DB::table('bakery_store_groups')->where('id', $id)->update($values);
            } else {
                $id = (int) DB::table('bakery_store_groups')->insertGetId($values + ['created_at' => now()]);
            }

            DB::table('bakery_store_group_members')->where('group_id', $id)->delete();
            $members = array_map(static fn (int|string $storeId): array => [
                'group_id' => $id,
                'bakery_id' => (int) $storeId,
            ], $data['stores'] ?? []);
            if ($members !== []) {
                DB::table('bakery_store_group_members')->insert($members);
            }
        });

        return redirect()->route('bakery.groups')->with('success', 'Группа сохранена.');
    }

    public function print(Request $request, int $group): View
    {
        $date = $this->date($request);
        $invoice = $this->invoice($group, $date);

        return view('bakery.group-print', [
            'group' => $invoice,
            'date' => $date,
            'generatedAt' => now('Europe/Moscow'),
            'downloadUrl' => route('bakery.group-xlsx', ['group' => $group, 'date' => $date]),
        ]);
    }

    public function download(Request $request, int $group): BinaryFileResponse
    {
        $date = $this->date($request);
        $invoice = $this->invoice($group, $date);

        return response()->download($this->workbook->create([], [], [$invoice], $date, now('Europe/Moscow')->format('d.m.Y H:i')), "gruppa-{$group}-{$date}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
