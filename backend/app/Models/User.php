<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\PermissionRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role_id', 'division_id', 'branch_id', 'name', 'email', 'password', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function ticketAssignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class, 'assigned_to');
    }

    public function qaAssignments(): HasMany
    {
        return $this->hasMany(TicketQaAssignment::class, 'qa_user_id');
    }

    public function qaTestRuns(): HasMany
    {
        return $this->hasMany(TicketQaTestRun::class, 'qa_user_id');
    }

    public function reportedDefects(): HasMany
    {
        return $this->hasMany(TicketQaDefect::class, 'reported_by');
    }

    public function assignedDefects(): HasMany
    {
        return $this->hasMany(TicketQaDefect::class, 'assigned_to');
    }

    public function uatAssignments(): HasMany
    {
        return $this->hasMany(TicketUatAssignment::class, 'requester_id');
    }

    public function uatRuns(): HasMany
    {
        return $this->hasMany(TicketUatRun::class, 'requester_id');
    }

    public function reportedUatFindings(): HasMany
    {
        return $this->hasMany(TicketUatFinding::class, 'reported_by');
    }

    public function assignedUatFindings(): HasMany
    {
        return $this->hasMany(TicketUatFinding::class, 'assigned_to');
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role?->key, (array) $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return app(PermissionRegistry::class)->userHas($this, $permission);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return app(PermissionRegistry::class)->forUser($this);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
