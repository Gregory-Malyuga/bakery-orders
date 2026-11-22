<?php

namespace Domains\Recipe\Presentation\Http\Controllers;

use Domains\Recipe\Application\Commands\Reset\ResetRecipeCommand;
use Domains\Recipe\Application\Commands\Reset\ResetRecipeHandler;
use Domains\Recipe\Application\Commands\SaveDough\SaveDoughRecipeCommand;
use Domains\Recipe\Application\Commands\SaveDough\SaveDoughRecipeHandler;
use Domains\Recipe\Application\Commands\SaveProduct\SaveProductRecipeCommand;
use Domains\Recipe\Application\Commands\SaveProduct\SaveProductRecipeHandler;
use Domains\Recipe\Application\Queries\GetEditor\GetRecipeEditorHandler;
use Domains\Recipe\Application\Queries\GetEditor\GetRecipeEditorQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController
{
    public function __construct(
        private readonly GetRecipeEditorHandler $editor,
        private readonly SaveProductRecipeHandler $saveProduct,
        private readonly SaveDoughRecipeHandler $saveDough,
        private readonly ResetRecipeHandler $resetRecipe,
    ) {}

    public function index(Request $request): View
    {
        /** @var array{section?:string,product?:int|string} $input */
        $input = $request->validate([
            'section' => ['nullable', 'in:products,dough'],
            'product' => ['nullable', 'integer', 'exists:bakery_products,id'],
        ]);
        $data = $this->editor->handle(new GetRecipeEditorQuery(
            $input['section'] ?? 'products',
            isset($input['product']) ? (int) $input['product'] : null,
        ));

        return view('bakery.recipes', $data->data);
    }

    public function saveProduct(Request $request): RedirectResponse
    {
        /** @var array{product:int|string,dough_type?:string,dough_grams?:int|float|numeric-string,ingredients?:list<array<string,mixed>>} $data */
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:bakery_products,id'],
            'dough_type' => ['nullable', 'in:main,samsa,none'],
            'dough_grams' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'ingredients' => ['sometimes', 'array', 'max:100'],
            'ingredients.*.ingredient_id' => ['nullable', 'integer'],
            'ingredients.*.name' => ['nullable', 'string', 'max:100'],
            'ingredients.*.amount' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'ingredients.*.unit' => ['nullable', 'in:g,pcs,tbsp'],
        ]);
        $this->saveProduct->handle(new SaveProductRecipeCommand($data));

        return redirect()->route('bakery.recipes', ['product' => $data['product']])->with('success', 'Рецептура изделия сохранена.');
    }

    public function saveDough(Request $request): RedirectResponse
    {
        /** @var array{kind:string,batch_size:int|float|numeric-string,ingredients:list<array<string,mixed>>} $data */
        $data = $request->validate([
            'kind' => ['required', 'in:main_dough,samsa_dough'],
            'batch_size' => $request->input('kind') === 'samsa_dough'
                ? ['required', 'integer', 'min:1', 'max:100000']
                : ['required', 'numeric', 'gt:0', 'max:100000'],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.ingredient_id' => ['nullable', 'integer'],
            'ingredients.*.name' => ['nullable', 'string', 'max:100'],
            'ingredients.*.amount' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);
        $this->saveDough->handle(new SaveDoughRecipeCommand($data));

        return redirect()->route('bakery.recipes', ['section' => 'dough'])->with('success', 'Рецептура теста сохранена.');
    }

    public function reset(Request $request): RedirectResponse
    {
        /** @var array{kind:string,product?:int|string} $data */
        $data = $request->validate(['kind' => ['required', 'in:product,main_dough,samsa_dough'], 'product' => ['required_if:kind,product', 'nullable', 'integer', 'exists:bakery_products,id']]);
        $productId = $data['product'] ?? null;
        $this->resetRecipe->handle(new ResetRecipeCommand($data));

        $query = $data['kind'] === 'product' ? ['product' => $productId] : ['section' => 'dough'];

        return redirect()->route('bakery.recipes', $query)->with('success', 'Восстановлены исходные нормы из таблицы.');
    }
}
