<?php

namespace Domains\Bakery\Presentation\Http\Requests;

use Domains\Bakery\Application\Data\UpdateBakeryData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBakeryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return UpdateBakeryData::rules();
    }
}
