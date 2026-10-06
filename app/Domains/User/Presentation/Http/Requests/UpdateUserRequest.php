<?php

namespace Domains\User\Presentation\Http\Requests;

use Domains\User\Application\Data\UpdateUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $id = (int) $this->route('user');

        return array_merge(
            UpdateUserData::rules(),
            ['email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)]],
        );
    }
}
