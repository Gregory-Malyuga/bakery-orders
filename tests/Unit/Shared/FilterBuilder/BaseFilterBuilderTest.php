<?php

namespace Tests\Unit\Shared\FilterBuilder;

use Domains\User\Domain\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Shared\FilterBuilder\BaseFilterBuilder;
use Shared\Filters\FilterInterface;
use Spatie\LaravelData\Data;
use Tests\TestCase;

class BaseFilterBuilderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_null_values_are_skipped(): void
    {
        $filters = new class(null) extends Data
        {
            public function __construct(
                public readonly ?string $name = null,
            ) {}
        };

        $query = User::query();
        $result = (new BaseFilterBuilder)->apply($query, $filters);

        $this->assertStringNotContainsString('where', strtolower($result->toRawSql()));
    }

    public function test_convention_camel_to_snake_where(): void
    {
        $filters = new class('test@example.com') extends Data
        {
            public function __construct(
                public readonly ?string $emailVerifiedAt = null,
            ) {}
        };

        $query = User::query();
        $result = (new BaseFilterBuilder)->apply($query, $filters);

        $this->assertStringContainsString('email_verified_at', $result->toRawSql());
    }

    public function test_filter_map_uses_custom_filter(): void
    {
        $filters = new class('alice') extends Data
        {
            public function __construct(
                public readonly ?string $name = null,
            ) {}
        };

        $customFilter = new class implements FilterInterface
        {
            /** {@inheritDoc} */
            public function apply(Builder $query, mixed $value): Builder
            {
                return $query->where('name', 'LIKE', "%{$value}%");
            }
        };

        $this->app->bind($customFilter::class, fn () => $customFilter);

        $query = User::query();
        $result = (new BaseFilterBuilder)->apply($query, $filters, [
            'name' => $customFilter::class,
        ]);

        $this->assertStringContainsString('LIKE', $result->toRawSql());
    }

    public function test_filters_narrow_results(): void
    {
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        $filters = new class('Alice') extends Data
        {
            public function __construct(
                public readonly ?string $name = null,
            ) {}
        };

        $query = User::query();
        $result = (new BaseFilterBuilder)->apply($query, $filters)->get();

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result->first()->name);
    }
}
