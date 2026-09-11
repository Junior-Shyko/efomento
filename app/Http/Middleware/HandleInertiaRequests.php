<?php

namespace App\Http\Middleware;

use App\Support\UploadLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
                'currentRoute' => Route::currentRouteName(),
            ],
            'allUnreadCount' => function () use ($request) {
                if (! $request->user()) {
                    return 0;
                }

                return $request->user()
                    ->unreadNotifications()
                    ->count();
            },
            'uploadLimits' => [
                'maxBytes' => UploadLimits::determineMaxUploadBytes(),
                'maxMb' => UploadLimits::maxUploadMb(),
            ],
        ];
    }
}
