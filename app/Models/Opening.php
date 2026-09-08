<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\AgentStatus;
use App\Enums\DocumentPhase;
use App\Enums\OpeningStatus;
use App\Traits\HasCreatedBy;
use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Opening extends Model implements Auditable
{
    use AuditableTrait, HasCreatedBy, HasFactory, HasFiles, SoftDeletes;

    protected $fillable = [
        'project_id',
        'opening_nup',
        'opening_date',
        'agent_status',
        'opened_by',
        'created_by',
        'creditor_number',
        'bank',
        'account_type',
        'branch',
        'account',
        'is_draft',
        'user_id',
        'status',
        'certificate_date',
        'supervisor_id',
        'started_at',
        'submitted_at',
        'concluded_at',
        'title_project',
        'registration_data',
    ];

    protected $casts = [
        'agent_status' => AgentStatus::class,
        'account_type' => AccountType::class,
        'status' => OpeningStatus::class,
        'opening_date' => 'date',
        'is_draft' => 'boolean',
        'certificate_date' => 'datetime',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'concluded_at' => 'datetime',
        'registration_data' => 'array',
    ];

    /** @var array<int, string> */
    public array $auditTags = [];

    public function generateTags(): array
    {
        return $this->auditTags;
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(OpeningSupervisor::class);
    }

    public function activeSupervisor(): HasOne
    {
        return $this->hasOne(OpeningSupervisor::class)
            ->ofMany(['assigned_at' => 'max'], fn ($q) => $q->where('is_active', true));
    }

    public function principalSupervisor(): HasOne
    {
        return $this->hasOne(OpeningSupervisor::class)
            ->ofMany(['assigned_at' => 'max'], fn ($q) => $q->where('is_active', true)->where('type', OpeningSupervisor::TYPE_PRINCIPAL));
    }

    public function isPrincipalSupervisor(User $user): bool
    {
        return $this->principalSupervisor?->user_id === $user->id;
    }

    public function alternateSupervisor(): HasOne
    {
        return $this->hasOne(OpeningSupervisor::class)
            ->ofMany(['assigned_at' => 'max'], fn ($q) => $q->where('is_active', true)->where('type', OpeningSupervisor::TYPE_ALTERNATE));
    }

    public function assignSupervisors(array $supervisors, ?int $assignedBy = null): void
    {
        foreach ($supervisors as $supervisor) {
            $type = $supervisor['type'] ?? null;
            $newUserId = $supervisor['id'] ?? null;

            if (! $type || ! $newUserId) {
                continue;
            }

            $currentActive = OpeningSupervisor::where('opening_id', $this->id)
                ->where('type', $type)
                ->where('is_active', true)
                ->first();

            if ($currentActive && (int) $currentActive->user_id === (int) $newUserId) {
                continue;
            }

            if ($currentActive) {
                $currentActive->update([
                    'is_active' => false,
                    'removed_at' => now(),
                ]);
            }

            OpeningSupervisor::create([
                'opening_id' => $this->id,
                'user_id' => $newUserId,
                'type' => $type,
                'assigned_by' => $assignedBy ?? Auth::id(),
                'assigned_at' => now(),
                'is_active' => true,
            ]);
        }
    }

    public function createCI(string $content): void
    {
        $this->project->documents()->create([
            'project_id' => $this->project_id,
            'notice_id' => $this->project->notice_id,
            'type' => 'ci',
            'body' => $content,
            'phase' => DocumentPhase::OPENING,
            'created_by' => auth()->id(),
        ]);
    }

    public function updateCI(string $content): void
    {
        $document = $this->project->documents()
            ->where('type', 'ci')
            ->where('phase', DocumentPhase::OPENING)
            ->latest()
            ->first();

        if ($document) {
            $document->update([
                'body' => $content,
            ]);
        }
    }
}
