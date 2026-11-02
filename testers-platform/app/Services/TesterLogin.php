<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class TesterLogin
{
    private const PHONE_LIMIT = 5;
    private const IP_LIMIT = 20;
    private const WINDOW_SECONDS = 900; // 15 minutes

    /** Finds the registered tester for this phone, with rate limiting. */
    public function find(Request $request, string $rawPhone): User
    {
        $phone = PhoneNormalizer::normalize($rawPhone);

        if ($phone === null) {
            $this->fail(422, 'Enter a valid Yemeni mobile number.', 'invalid_phone', [
                'phone' => ['Enter a valid Yemeni mobile number.'],
            ]);
        }

        $phoneKey = 'tester-login:phone:' . $phone;
        $ipKey = 'tester-login:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($phoneKey, self::PHONE_LIMIT)
            || RateLimiter::tooManyAttempts($ipKey, self::IP_LIMIT)) {
            $this->fail(429, 'Too many attempts. Try again later.', 'too_many_attempts');
        }

        $tester = User::where('role', Role::Tester->value)->where('phone', $phone)->first();

        if (! $tester) {
            RateLimiter::hit($phoneKey, self::WINDOW_SECONDS);
            RateLimiter::hit($ipKey, self::WINDOW_SECONDS);

            $this->fail(404, 'This number is not registered. Please register through an invitation link.', 'not_registered');
        }

        RateLimiter::clear($phoneKey);

        return $tester;
    }

    /** Starts a long session (30 days or more, set by SESSION_LIFETIME). */
    public function startSession(Request $request, User $tester): void
    {
        Auth::guard('web')->login($tester);
        $request->session()->regenerate();
        $tester->update(['last_login_at' => now()]);
    }

    private function fail(int $status, string $message, string $code, array $errors = []): never
    {
        $body = ['message' => $message, 'code' => $code];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        throw new HttpResponseException(response()->json($body, $status));
    }
}