<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function adminLogin(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Rate limit per account and per IP
        $keys = [
            'admin-login:account:' . Str::lower($data['username']),
            'admin-login:ip:' . $request->ip(),
        ];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
            }
        }

        $user = User::where('username', $data['username'])
            ->whereIn('role', ['admin', 'super_admin'])
            ->first();

        if (! $user || $user->status !== 'active' || ! Hash::check($data['password'], $user->password)) {
            foreach ($keys as $key) {
                RateLimiter::hit($key, 900); // lock window: 15 minutes
            }

            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        foreach ($keys as $key) {
            RateLimiter::clear($key);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return response()->json(['data' => $user, 'message' => 'Logged in.']);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }
}