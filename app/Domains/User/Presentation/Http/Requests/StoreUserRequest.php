<?php

namespace Domains\User\Presentation\Http\Requests;

use Domains\User\Application\Data\CreateUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return array_merge(
            CreateUserData::rules(),
            ['email' => ['required', 'email', 'max:255', Rule::unique('users')]],
        );
    }
}
