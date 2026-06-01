<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'title',
        'description',
        'status',
        'priority',
        'category',
        'reported_by_id',
        'assigned_to_id',
        'assigned_to_name',
        'comment_count',
    ];

    protected $casts = [
        'comment_count' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

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
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'ticket_id')->orderBy('created_at', 'asc');
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class, 'ticket_id');
    }

    public function notifications()
    {
        return $this->hasMany(TicketNotification::class, 'related_ticket_id');
    }
}
