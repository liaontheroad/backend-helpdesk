<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'role',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    // Auto-generate UUID on create
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function reportedTickets()
    {
        return $this->hasMany(Ticket::class, 'reported_by_id');
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'author_id');
    }

    public function notifications()
    {
        return $this->hasMany(TicketNotification::class, 'user_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHelpdesk(): bool
    {
        return in_array($this->role, ['helpdesk', 'admin']);
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }
}
