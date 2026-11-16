<?php

namespace Domains\User\Domain\Models;

use Domains\User\Domain\Database\Factories\UserFactory;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property UserStatus $status
 * @property int|null $bakery_id
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Domains\User\Domain\Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password', 'role', 'status', 'bakery_id'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'status'];

    /** @return list<int> */
    public function accessibleStoreIds(): array
    {
        $ids = [];
        foreach (DB::table('bakery_account_stores')->where('user_id', $this->id)->pluck('bakery_id') as $storeId) {
            if (is_int($storeId) || is_string($storeId)) {
                $ids[] = (int) $storeId;
            }
        }
        if ($this->bakery_id !== null && ! in_array((int) $this->bakery_id, $ids, true)) {
            $ids[] = (int) $this->bakery_id;
        }

        return $ids;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** @param array<UserRole|string> $roles */
    public function hasAnyRole(array $roles): bool
    {
        /** @var UserRole $current */
        $current = $this->role;

        foreach ($roles as $role) {
            if ($role instanceof UserRole && $role === $current) {
                return true;
            }
            if (is_string($role) && $role === $current->value) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }
}
