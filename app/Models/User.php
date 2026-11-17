<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /** Roles a partner's people can hold. All of them may update WBS progress and evidence. */
    public const PARTNER_ROLES = [
        'project_manager' => 'Project Manager',
        'project_admin' => 'Project Admin',
        'business_analyst' => 'Business Analyst',
    ];

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'partner_id', 'partner_role', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /** A TIF admin: manages partners, accounts, projects, baselines and the Drive connection. */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPartner(): bool
    {
        return $this->role === 'partner';
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** Admins and partner staff (PM, Project Admin, BA) may update progress and evidence. */
    public function canUpdateWbs(): bool
    {
        return $this->isAdmin() || ($this->isPartner() && array_key_exists((string) $this->partner_role, self::PARTNER_ROLES));
    }

    /** The partner's Project Manager starts WBS steps and submits them for review. */
    public function canStartAndSubmit(): bool
    {
        return $this->isPartner() && $this->partner_role === 'project_manager';
    }

    /** TIF admins accept finished steps or return them for revision. */
    public function canReview(): bool
    {
        return $this->isAdmin();
    }

    /** Only TIF admins may change the baseline (weights and plan dates). */
    public function canEditBaseline(): bool
    {
        return $this->isAdmin();
    }

    public function roleLabel(): string
    {
        return $this->isAdmin() ? 'TIF Admin' : (self::PARTNER_ROLES[$this->partner_role] ?? 'Partner');
    }

    /** Readable one-time password handed to the user by the admin, e.g. "kite-4821-river". */
    public static function temporaryPassword(): string
    {
        $words = ['amber', 'birch', 'cedar', 'delta', 'ember', 'fjord', 'grove', 'harbor', 'indigo', 'jasper',
            'kite', 'lumen', 'maple', 'nova', 'orbit', 'pine', 'quartz', 'river', 'sage', 'tide', 'umber', 'vale', 'willow', 'zephyr'];

        return $words[random_int(0, count($words) - 1)].'-'.random_int(1000, 9999).'-'.$words[random_int(0, count($words) - 1)].Str::lower(Str::random(2));
    }
}
