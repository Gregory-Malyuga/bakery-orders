<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImpersonationController
{
    private const SESSION_KEY = 'bakery.impersonating_admin_id';

    public function start(Request $request, User $user): RedirectResponse
    {
        abort_if($request->session()->has(self::SESSION_KEY), 403);
        abort_unless($user->role === UserRole::User && $user->status === UserStatus::Verify, 404);

        $adminId = $request->user()?->id;
        abort_unless($adminId !== null, 403);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $adminId);
        Log::info('Bakery admin started user session', ['admin_id' => $adminId, 'user_id' => $user->id]);

        return redirect()->route('bakery.store');
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get(self::SESSION_KEY);
        abort_unless(is_int($adminId) && $request->user()?->role === UserRole::User, 403);

        $admin = User::query()->find($adminId);
        if ($admin === null || $admin->role !== UserRole::Admin || $admin->status !== UserStatus::Verify) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('bakery.login');
        }

        $userId = $request->user()?->id;
        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_KEY);
        Log::info('Bakery admin returned from user session', ['admin_id' => $admin->id, 'user_id' => $userId]);

        return redirect()->route('bakery.catalog', ['tab' => 'accounts']);
    }
}
