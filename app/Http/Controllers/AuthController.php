<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Analytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt($credentials, true)) {
            return back()->withErrors(['email' => 'Those credentials do not match.'])->onlyInput('email');
        }

        if (Auth::user()->isSuspended()) {
            Auth::logout();

            return back()->withErrors(['email' => 'This account has been suspended.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        app(Analytics::class)->event('login', ['method' => 'email']);

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        Auth::login(User::create($data), true);
        $request->session()->regenerate();
        app(Analytics::class)->event('sign_up', ['method' => 'email']);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
