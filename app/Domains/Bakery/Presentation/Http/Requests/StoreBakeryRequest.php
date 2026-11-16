<?php

namespace Domains\Bakery\Presentation\Http\Requests;

use Domains\Bakery\Application\Data\CreateBakeryData;
use Illuminate\Foundation\Http\FormRequest;

class StoreBakeryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return CreateBakeryData::rules();
    }
}
