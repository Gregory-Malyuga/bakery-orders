<?php

namespace Domains\User\Infrastructure\Repositories;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Exceptions\UserNotFoundException;
use Domains\User\Domain\Models\User;
use Domains\User\Infrastructure\Filters\EmailFilter;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;
use Shared\Filters\FilterInterface;
use Shared\Repository\BaseRepository;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected string $model = User::class;

    protected string $notFoundException = UserNotFoundException::class;

    /** @var array<string, class-string<FilterInterface>> */
    protected array $filterMap = [
        'email' => EmailFilter::class,
    ];

    public function create(array $data): User
    {
        /** @var User */
        return parent::create($data);
    }

    public function findOrFail(int|string $id): User
    {
        /** @var User */
        return parent::findOrFail($id);
    }

    public function update(Model $model, array $data): User
    {
        /** @var User */
        return parent::update($model, $data);
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        return $user;
    }

    public function deleteToken(int $tokenId): void
    {
        PersonalAccessToken::whereKey($tokenId)->delete();
    }
}
