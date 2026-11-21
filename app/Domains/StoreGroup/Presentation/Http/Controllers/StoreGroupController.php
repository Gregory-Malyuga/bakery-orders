<?php

namespace Domains\StoreGroup\Presentation\Http\Controllers;

use Domains\Bakery\Application\GroupInvoiceService;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Invoice\Application\Exports\InvoiceWorkbookInterface;
use Domains\StoreGroup\Application\Commands\Save\SaveStoreGroupCommand;
use Domains\StoreGroup\Application\Commands\Save\SaveStoreGroupHandler;
use Domains\StoreGroup\Application\Queries\ListAll\ListStoreGroupsHandler;
use Domains\StoreGroup\Application\Queries\ListAll\ListStoreGroupsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @phpstan-import-type GroupInvoice from \Domains\Bakery\Application\GroupInvoiceService */
class StoreGroupController
{
    public function __construct(
        private readonly OrderingRepositoryInterface $orders,
        private readonly GroupInvoiceService $invoices,
        private readonly InvoiceWorkbookInterface $workbook,
        private readonly ListStoreGroupsHandler $listGroups,
        private readonly SaveStoreGroupHandler $saveGroup,
    ) {}

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
        $result = $this->listGroups->handle(new ListStoreGroupsQuery);
        $groups = $result->groups;
        $members = $result->members;

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

        $this->saveGroup->handle(new SaveStoreGroupCommand(
            isset($data['id']) ? (int) $data['id'] : null,
            $data['name'],
            (bool) ($data['show_in_invoice'] ?? false),
            (bool) ($data['exclude_from_labels'] ?? false),
            array_map(static fn (int|string $id): int => (int) $id, $data['stores'] ?? []),
        ));

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
