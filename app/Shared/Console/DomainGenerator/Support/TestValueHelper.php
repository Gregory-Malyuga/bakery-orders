<?php

namespace Shared\Console\DomainGenerator\Support;

use Illuminate\Support\Str;

class TestValueHelper
{
    public function valueFor(string $fieldName, string $phpType, bool $alternate = false): string
    {
        if ($phpType === 'int') {
            return $alternate ? '99' : '42';
        }

        if ($phpType === 'float') {
            return $alternate ? '99.99' : '42.50';
        }

        if ($phpType === 'bool') {
            return $alternate ? 'false' : 'true';
        }

        if ($phpType === 'array') {
            return '[]';
        }

        if (str_ends_with($fieldName, 'email')) {
            return $alternate ? "'updated@example.com'" : "'test@example.com'";
        }

        if (str_ends_with($fieldName, 'phone') || str_ends_with($fieldName, 'tel')) {
            return $alternate ? "'+7 800 000 0001'" : "'+7 800 000 0000'";
        }

        if (str_contains($fieldName, 'first_name')) {
            return $alternate ? "'Jane'" : "'John'";
        }

        if (str_contains($fieldName, 'last_name')) {
            return $alternate ? "'Smith'" : "'Doe'";
        }

        if (str_contains($fieldName, 'address')) {
            return $alternate ? "'456 Other Ave'" : "'123 Test Street'";
        }

        if (str_contains($fieldName, 'city')) {
            return $alternate ? "'Other City'" : "'Test City'";
        }

        if (str_contains($fieldName, 'country')) {
            return $alternate ? "'UK'" : "'US'";
        }

        if (str_contains($fieldName, 'description') || str_contains($fieldName, 'note') || str_contains($fieldName, 'comment')) {
            return $alternate ? "'Updated description'" : "'Test description'";
        }

        if (str_contains($fieldName, 'title')) {
            return $alternate ? "'Other Title'" : "'Test Title'";
        }

        if (str_contains($fieldName, 'url') || str_contains($fieldName, 'website') || str_contains($fieldName, 'link')) {
            return $alternate ? "'http://other.example.com'" : "'http://test.example.com'";
        }

        $label = Str::headline($fieldName);

        return $alternate ? "'Updated {$label}'" : "'Test {$label}'";
    }
}
