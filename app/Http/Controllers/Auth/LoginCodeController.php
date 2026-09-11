<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginCodeController extends Controller
{
    public function show(Request $request, LoginCodeService $codes): Response|RedirectResponse
    {
        if (! $codes->pendingUser($request)) {
            return $this->expired($request, $codes);
        }

        return Inertia::render('Auth/LoginCode', ['status' => session('status')]);
    }

    public function verify(Request $request, LoginCodeService $codes): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        $user = $codes->verify($request, $request->string('code')->toString());
        if (! $user) {
            return $this->expired($request, $codes);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('notices.index', absolute: false));
    }

    public function resend(Request $request, LoginCodeService $codes): RedirectResponse
    {
        $user = $codes->pendingUser($request);
        if (! $user) {
            return $this->expired($request, $codes);
        }

        $codes->send($request, $user);

        return redirect()->route('two-factor.show')->with('status', 'Um novo código foi enviado ao seu email.');
    }

    public function cancel(Request $request, LoginCodeService $codes): RedirectResponse
    {
        $codes->cancel($request);
        $request->session()->regenerate();

        return redirect()->route('login');
    }

    private function expired(Request $request, LoginCodeService $codes): RedirectResponse
    {
        $codes->cancel($request);

        return redirect()->route('login')->with('status', 'O código expirou ou a solicitação não é mais válida. Entre novamente.');
    }
}
