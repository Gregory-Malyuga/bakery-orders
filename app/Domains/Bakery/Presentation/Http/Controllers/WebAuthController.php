<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WebAuthController
{
    public function loginForm(): View
    {
        return view('bakery.login');
    }

    public function login(Request $request): RedirectResponse
    {
        /** @var array{email:string,password:string} $credentials */
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, false)) {
            return back()->withErrors(['email' => 'Неверная почта или пароль.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $user = $request->user();
        if ($user === null || $user->status->value !== 'verify' || ! in_array($user->role->value, ['admin', 'user'], true)) {
            Auth::logout();

            return back()->withErrors(['email' => 'Аккаунт не активен.']);
        }

        return redirect()->route($user->role->value === 'admin' ? 'bakery.admin' : 'bakery.store');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('bakery.login');
    }
}
