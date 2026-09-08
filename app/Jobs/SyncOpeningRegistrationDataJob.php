<?php

namespace App\Jobs;

use App\Models\Opening;
use App\Services\MapasClient;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncOpeningRegistrationDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public int $timeout = 120;

    public int $maxExceptions = 3;

    public function __construct(
        public int $projectId,
        public int $registrationId,
        public ?int $userId = null,
    ) {
        $this->onQueue('details');
    }

    public function middleware(): array
    {
        return [
            (new RateLimited('mapas-api'))->releaseAfter(30),

            (new WithoutOverlapping("sync-opening-registration-data:{$this->registrationId}"))
                ->releaseAfter(60)
                ->expireAfter(600),
        ];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(MapasClient $mapasClient): void
    {
        Log::info('sync.opening.registration_data.start', [
            'project_id' => $this->projectId,
            'registration_id' => $this->registrationId,
        ]);

        $details = $mapasClient->registrationDetails($this->registrationId);

        $safeRegistration = [
            'registration' => [
                'id' => data_get($details, 'registration.id'),
                'number' => data_get($details, 'registration.number'),
                'status' => data_get($details, 'registration.status'),
                'files' => data_get($details, 'registration.files', []),
            ],
            'fileConfigurations' => data_get($details, 'fileConfigurations', []),
            'fields' => data_get($details, 'fields', []),
        ];

        $openings = Opening::where('project_id', $this->projectId)->get();

        if ($openings->isEmpty()) {
            Log::warning('sync.opening.registration_data.no_opening', [
                'project_id' => $this->projectId,
                'registration_id' => $this->registrationId,
            ]);

            return;
        }

        $this->updateOpeningsAsImporter($openings, $safeRegistration);

        Log::info('sync.opening.registration_data.done', [
            'project_id' => $this->projectId,
            'registration_id' => $this->registrationId,
        ]);
    }

    private function updateOpeningsAsImporter($openings, array $safeRegistration): void
    {
        $this->actingAsImporter(function () use ($openings, $safeRegistration): void {
            foreach ($openings as $opening) {
                $opening->auditTags = ['mapas-registration-sync'];
                $opening->update(['registration_data' => $safeRegistration]);
            }
        });
    }

    private function actingAsImporter(callable $callback): void
    {
        if ($this->userId === null) {
            $callback();

            return;
        }

        $previousUser = Auth::user();
        Auth::onceUsingId($this->userId);

        try {
            $callback();
        } finally {
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetUser();
            }
        }
    }

    public function backoff(): array
    {
        return [1, 5, 10, 30, 60];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('sync.opening.registration_data.failed', [
            'project_id' => $this->projectId,
            'registration_id' => $this->registrationId,
            'message' => $exception->getMessage(),
        ]);
    }
}
