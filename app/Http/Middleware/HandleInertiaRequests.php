<?php

namespace App\Http\Middleware;

use App\Enums\ServerStatus;
use App\GameManager;
use App\Models\Server;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'appVersion' => config('app.version'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
            ],
            'gameTypeLabels' => fn () => collect(app(GameManager::class)->allHandlers())
                ->mapWithKeys(fn ($handler, string $key): array => [$key => $handler->label()])
                ->toArray(),
            'activeServers' => fn () => $request->user()
                ? Server::query()
                    ->whereIn('status', array_map(
                        fn (ServerStatus $s) => $s->value,
                        array_filter(ServerStatus::cases(), fn (ServerStatus $s) => $s->isActive()),
                    ))
                    ->get(['id', 'name', 'status'])
                    ->map(fn (Server $s): array => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'status' => $s->status->value,
                    ])
                    ->values()
                    ->toArray()
                : [],
        ];
    }
}
