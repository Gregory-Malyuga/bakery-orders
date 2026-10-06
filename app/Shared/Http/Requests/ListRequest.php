<?php

namespace Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shared\Data\PaginationData;
use Shared\Data\SortData;

abstract class ListRequest extends FormRequest
{
    /** @var list<string> */
    protected const array SORTABLE = ['id'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return array_merge(
            SortData::rules(),
            PaginationData::rules(),
            ['sort' => ['sometimes', 'string', Rule::in(static::SORTABLE)]],
        );
    }

    public function toSort(): SortData
    {
        return SortData::fromRequest($this);
    }

    public function toPagination(): PaginationData
    {
        return PaginationData::fromRequest($this);
    }
}
