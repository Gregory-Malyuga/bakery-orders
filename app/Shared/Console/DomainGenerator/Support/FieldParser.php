<?php

namespace Shared\Console\DomainGenerator\Support;

class FieldParser
{
    private const array SKIP = ['id', 'created_at', 'updated_at'];

    /**
     * @param  list<string>  $rawFields
     * @return array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>
     */
    public function parse(array $rawFields): array
    {
        $result = [];

        foreach ($rawFields as $raw) {
            $parts = explode(':', $raw, 2);
            $fieldName = $parts[0];
            $explicitType = $parts[1] ?? '';

            if (in_array($fieldName, self::SKIP, true)) {
                continue;
            }

            $type = $explicitType !== '' ? $explicitType : $this->inferType($fieldName);
            $result[$fieldName] = $this->typeDefinition($fieldName, $type);
        }

        return $result;
    }

    private function inferType(string $fieldName): string
    {
        return match (true) {
            str_ends_with($fieldName, 'email') => 'email',
            str_ends_with($fieldName, 'phone') || str_ends_with($fieldName, 'tel') => 'phone',
            str_ends_with($fieldName, '_id') => 'integer',
            str_ends_with($fieldName, '_at') => 'timestamp',
            str_ends_with($fieldName, '_date') => 'date',
            str_ends_with($fieldName, '_count') || str_ends_with($fieldName, '_amount') => 'integer',
            str_ends_with($fieldName, '_price') || str_ends_with($fieldName, '_cost') => 'float',
            str_starts_with($fieldName, 'is_') || str_starts_with($fieldName, 'has_') => 'boolean',
            default => 'string',
        };
    }

    /**
     * @return array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}
     */
    private function typeDefinition(string $fieldName, string $type): array
    {
        $faker = $this->fakerForField($fieldName, $type);

        return match ($type) {
            'integer', 'int' => ['phpType' => 'int', 'nullable' => false, 'migration' => 'integer', 'decimal' => false, 'faker' => $faker, 'rules' => ['integer']],
            'float', 'decimal', 'numeric' => ['phpType' => 'float', 'nullable' => false, 'migration' => 'decimal', 'decimal' => true, 'faker' => $faker, 'rules' => ['numeric']],
            'boolean', 'bool' => ['phpType' => 'bool', 'nullable' => false, 'migration' => 'boolean', 'decimal' => false, 'faker' => $faker, 'rules' => ['boolean']],
            'text' => ['phpType' => 'string', 'nullable' => false, 'migration' => 'text', 'decimal' => false, 'faker' => $faker, 'rules' => ['string']],
            'email' => ['phpType' => 'string', 'nullable' => false, 'migration' => 'string', 'decimal' => false, 'faker' => $faker, 'rules' => ['string', 'email', 'max:255']],
            'phone' => ['phpType' => 'string', 'nullable' => false, 'migration' => 'string', 'decimal' => false, 'faker' => $faker, 'rules' => ['string', 'max:30']],
            'date' => ['phpType' => 'string', 'nullable' => false, 'migration' => 'date', 'decimal' => false, 'faker' => $faker, 'rules' => ['date']],
            'timestamp', 'datetime' => ['phpType' => 'string', 'nullable' => true, 'migration' => 'timestamp', 'decimal' => false, 'faker' => $faker, 'rules' => ['nullable', 'date']],
            'json', 'array' => ['phpType' => 'array', 'nullable' => true, 'migration' => 'json', 'decimal' => false, 'faker' => $faker, 'rules' => ['nullable', 'array']],
            default => ['phpType' => 'string', 'nullable' => false, 'migration' => 'string', 'decimal' => false, 'faker' => $faker, 'rules' => ['string', 'max:255']],
        };
    }

    private function fakerForField(string $fieldName, string $type): string
    {
        return match (true) {
            str_contains($fieldName, 'first_name') => 'fake()->firstName()',
            str_contains($fieldName, 'last_name') => 'fake()->lastName()',
            str_contains($fieldName, 'name') && ! str_contains($fieldName, 'user') => 'fake()->name()',
            str_ends_with($fieldName, 'email') => 'fake()->unique()->safeEmail()',
            str_ends_with($fieldName, 'phone') || str_ends_with($fieldName, 'tel') => 'fake()->phoneNumber()',
            str_contains($fieldName, 'address') => 'fake()->address()',
            str_contains($fieldName, 'city') => 'fake()->city()',
            str_contains($fieldName, 'country') => 'fake()->country()',
            str_contains($fieldName, 'description') || str_contains($fieldName, 'note') || str_contains($fieldName, 'comment') => 'fake()->paragraph()',
            str_contains($fieldName, 'title') => 'fake()->sentence(4)',
            str_contains($fieldName, 'url') || str_contains($fieldName, 'website') || str_contains($fieldName, 'link') => 'fake()->url()',
            $type === 'integer' || $type === 'int' => 'fake()->randomNumber()',
            $type === 'float' || $type === 'decimal' => 'fake()->randomFloat(2, 0, 1000)',
            $type === 'boolean' || $type === 'bool' => 'fake()->boolean()',
            $type === 'text' => 'fake()->paragraph()',
            $type === 'date' => 'fake()->date()',
            $type === 'timestamp' || $type === 'datetime' => "fake()->dateTime()->format('Y-m-d H:i:s')",
            $type === 'json' || $type === 'array' => '[]',
            default => 'fake()->words(2, true)',
        };
    }
}
