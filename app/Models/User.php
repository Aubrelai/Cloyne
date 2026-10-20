<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * A person who can sign in to Cloyne.
 *
 * Roles: owner (everything, including the team), reviewer (searches, audits,
 * deletes, and later approving emails), viewer (look only).
 */
#[Fillable(['name', 'email', 'password', 'role', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'owner' => ['label' => 'Owner', 'about' => 'Everything, including the team'],
        'reviewer' => ['label' => 'Reviewer', 'about' => 'Run searches and audits, delete leads, approve emails'],
        'viewer' => ['label' => 'Viewer', 'about' => 'Look only, no changes'],
    ];

    public const MIN_PASSWORD = 10;

    protected $attributes = [
        'role' => 'viewer',
        'must_change_password' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /** Owners and reviewers can change data; viewers can only look. */
    public function canEdit(): bool
    {
        return in_array($this->role, ['owner', 'reviewer'], true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role]['label'] ?? Str::headline($this->role);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last) ?: '?';
    }

    /** A readable temporary password, e.g. "kite-4821-river". */
    public static function temporaryPassword(): string
    {
        $words = ['amber', 'birch', 'cedar', 'delta', 'ember', 'fjord', 'grove', 'harbor', 'island', 'jasper',
            'kite', 'lumen', 'maple', 'north', 'onyx', 'pine', 'quartz', 'river', 'slate', 'tide', 'umber', 'vale', 'willow', 'zenith'];

        return $words[random_int(0, count($words) - 1)].'-'.random_int(1000, 9999).'-'
            .$words[random_int(0, count($words) - 1)].'-'.random_int(10, 99);
    }

    public static function ownerCount(): int
    {
        return static::where('role', 'owner')->count();
    }
}
