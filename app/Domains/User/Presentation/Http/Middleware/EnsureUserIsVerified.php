<?php

namespace Domains\User\Presentation\Http\Middleware;

use Closure;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Exceptions\UserNotVerifiedException;
use Domains\User\Domain\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('features.user_status_gate')) {
            /** @var Response */
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || $user->status !== UserStatus::Verify) {
            throw new UserNotVerifiedException;
        }

        /** @var Response */
        return $next($request);
    }
}
