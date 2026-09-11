<?php

namespace App\Services;

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LoginCodeService
{
    public function send(Request $request, User $user): void
    {
        $key = 'login-code:send:'.$user->id;
        if (! Cache::add($key, true, 60)) {
            throw ValidationException::withMessages(['email' => 'Aguarde 60 segundos antes de solicitar outro código.']);
        }

        $this->cancel($request);
        $token = Str::random(64);
        $code = (string) random_int(100000, 999999);
        $challenge = [
            'user_id' => $user->id,
            'hash' => Hash::make($code),
            'credentials' => $this->fingerprint($user),
        ];

        try {
            Mail::to($user->email)->send(new LoginCodeMail($code));
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['email' => 'Não foi possível enviar o código. Aguarde um minuto e tente entrar novamente.']);
        }

        Cache::put('login-code:'.$token, $challenge, now()->addMinutes(10));
        $request->session()->put('login_code', $token);
    }

    public function pendingUser(Request $request): ?User
    {
        $token = $request->session()->get('login_code');
        $challenge = $token ? Cache::get('login-code:'.$token) : null;
        $user = $challenge ? User::find($challenge['user_id']) : null;

        return $user && hash_equals($challenge['credentials'], $this->fingerprint($user)) ? $user : null;
    }

    public function verify(Request $request, string $code): ?User
    {
        $token = $request->session()->get('login_code');
        if (! $token || ! ($pendingUser = $this->pendingUser($request))) {
            return null;
        }

        return Cache::lock('login-code:lock:'.$pendingUser->id, 10)->get(function () use ($request, $token, $code) {
            $user = $this->pendingUser($request);
            if (! $user) {
                return null;
            }

            $attempts = 'login-code:attempts:'.$user->id;
            if (RateLimiter::tooManyAttempts($attempts, 5)) {
                throw ValidationException::withMessages(['code' => 'Limite de tentativas atingido. Aguarde 10 minutos para tentar novamente.']);
            }

            RateLimiter::hit($attempts, 600);
            $challenge = Cache::get('login-code:'.$token);
            if (! Hash::check($code, $challenge['hash'])) {
                throw ValidationException::withMessages(['code' => 'Código inválido. Confira o código recebido por email.']);
            }

            $this->cancel($request);
            RateLimiter::clear($attempts);

            return $user;
        }) ?: null;
    }

    public function cancel(Request $request): void
    {
        $token = $request->session()->pull('login_code');
        if ($token) {
            Cache::forget('login-code:'.$token);
        }
    }

    private function fingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->email.'|'.$user->getAuthPassword(), config('app.key'));
    }
}
