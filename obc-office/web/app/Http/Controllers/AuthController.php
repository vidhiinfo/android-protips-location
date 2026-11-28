<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        $key = Str::lower($data['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $field = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        try {
            $ok = Auth::attempt([$field => $data['username'], 'password' => $data['password'], 'active' => true], $request->boolean('remember'));
        } catch (\RuntimeException $e) {
            // The stored password is not a Laravel (bcrypt) hash, e.g. it was edited directly in phpMyAdmin.
            report($e);
            throw ValidationException::withMessages(['username' => 'This account\'s password must be reset by the administrator (it was changed outside the portal).']);
        }
        if (! $ok) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['username' => 'Invalid username or password.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('login');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
