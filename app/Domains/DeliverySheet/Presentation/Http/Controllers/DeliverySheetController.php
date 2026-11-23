<?php

namespace Domains\DeliverySheet\Presentation\Http\Controllers;

use Domains\DeliverySheet\Application\Commands\Create\CreateSheetCommand;
use Domains\DeliverySheet\Application\Commands\Create\CreateSheetHandler;
use Domains\DeliverySheet\Application\Commands\Delete\DeleteSheetCommand;
use Domains\DeliverySheet\Application\Commands\Delete\DeleteSheetHandler;
use Domains\DeliverySheet\Application\Commands\Print\PrintSheetCommand;
use Domains\DeliverySheet\Application\Commands\Print\PrintSheetHandler;
use Domains\DeliverySheet\Application\Commands\Update\UpdateSheetCommand;
use Domains\DeliverySheet\Application\Commands\Update\UpdateSheetHandler;
use Domains\DeliverySheet\Application\Queries\Edit\EditSheetHandler;
use Domains\DeliverySheet\Application\Queries\Edit\EditSheetQuery;
use Domains\DeliverySheet\Application\Queries\Get\GetSheetHandler;
use Domains\DeliverySheet\Application\Queries\Get\GetSheetQuery;
use Domains\DeliverySheet\Application\Queries\List\ListSheetsHandler;
use Domains\DeliverySheet\Application\Queries\List\ListSheetsQuery;
use Domains\DeliverySheet\Application\Queries\Printed\PrintedSheetHandler;
use Domains\DeliverySheet\Application\Queries\Printed\PrintedSheetQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

readonly class DeliverySheetController
{
    public function __construct(
        private ListSheetsHandler $list,
        private GetSheetHandler $get,
        private EditSheetHandler $edit,
        private PrintedSheetHandler $printed,
        private CreateSheetHandler $create,
        private UpdateSheetHandler $update,
        private DeleteSheetHandler $delete,
        private PrintSheetHandler $print,
    ) {}

    public function index(Request $request): View
    {
        /** @var array{date?:string,copy?:int|string} $input */
        $input = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'copy' => ['nullable', 'integer', Rule::exists('bakery_delivery_sheets', 'id')],
        ]);

        $data = $this->list->handle(
            new ListSheetsQuery(
                $input['date'] ?? null,
                isset($input['copy']) ? (int) $input['copy'] : null,
            ),
        );

        return view('bakery.delivery.delivery-routes', $data->data);
    }

    public function createSheet(Request $request): RedirectResponse
    {
        $data = $this->validateSheet($request);
        $id = $this->create->handle(new CreateSheetCommand($data));

        return redirect()->route('bakery.delivery-sheet', $id);
    }

    public function printSheet(Request $request): View
    {
        /** @var array{sheet_id:int|string} $data */
        $data = $request->validate([
            'sheet_id' => ['required', 'integer', Rule::exists('bakery_delivery_sheets', 'id')],
        ]);

        $printId = $this->print->handle(new PrintSheetCommand((int) $data['sheet_id']));
        assert(is_int($printId));
        $printed = $this->printed->handle(new PrintedSheetQuery($printId));

        return view('bakery.delivery.delivery-sheet', [
            'sheet' => $printed->data['sheet'],
            'autoPrint' => true,
            'printId' => $printId,
        ]);
    }

    public function printedSheet(int $print): View
    {
        return view(
            'bakery.delivery.delivery-sheet',
            $this->printed->handle(new PrintedSheetQuery($print))->data,
        );
    }

    public function editSheet(int $sheet): View
    {
        return view(
            'bakery.delivery.delivery-sheet-edit',
            $this->edit->handle(new EditSheetQuery($sheet))->data,
        );
    }

    public function updateSheet(Request $request, int $sheet): RedirectResponse
    {
        $data = $this->validateSheet($request);
        $this->update->handle(new UpdateSheetCommand($sheet, $data));

        return redirect()
            ->route('bakery.delivery-sheet', $sheet)
            ->with('success', 'Лист обновлён.');
    }

    public function sheet(int $sheet): View
    {
        return view(
            'bakery.delivery.delivery-sheet',
            $this->get->handle(new GetSheetQuery($sheet))->data,
        );
    }

    public function deleteSheet(int $sheet): RedirectResponse
    {
        $this->delete->handle(new DeleteSheetCommand($sheet));

        return redirect()
            ->route('bakery.delivery-routes')
            ->with('success', 'Маршрутный лист удалён.');
    }

    /** @return array{date:string,name:string,driver:string,salary?:string,bonus?:string,stores:list<int|string>} */
    private function validateSheet(Request $request): array
    {
        /**
         * @var array{date:string,name:string,driver:string,salary?:string,bonus?:string,
         *     stores:list<int|string>} $data
         */
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'max:255'],
            'salary' => ['nullable', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            'bonus' => ['nullable', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            'stores' => ['required', 'array', 'min:1'],
            'stores.*' => ['required', 'integer', 'distinct', Rule::exists('bakeries', 'id')],
        ]);

        return $data;
    }
}
