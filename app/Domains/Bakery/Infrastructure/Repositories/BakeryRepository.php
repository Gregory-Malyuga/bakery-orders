<?php

namespace Domains\Bakery\Infrastructure\Repositories;

use Domains\Bakery\Application\Repositories\BakeryRepositoryInterface;
use Domains\Bakery\Domain\Models\Bakery;
use Illuminate\Database\Eloquent\Model;
use Shared\Repository\BaseRepository;

class BakeryRepository extends BaseRepository implements BakeryRepositoryInterface
{
    protected string $model = Bakery::class;

    public function findOrFail(int|string $id): Bakery
    {
        /** @var Bakery */
        return parent::findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Bakery
    {
        /** @var Bakery */
        return parent::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Model $model, array $data): Bakery
    {
        /** @var Bakery */
        return parent::update($model, $data);
    }
}
