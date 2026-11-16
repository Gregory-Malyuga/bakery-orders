<?php

namespace Domains\Bakery\Application\Data;

use Shared\Http\Data\BaseData;

class CreateBakeryData extends BaseData
{
    public function __construct(
        public readonly string $name,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
        ];
    }
}
