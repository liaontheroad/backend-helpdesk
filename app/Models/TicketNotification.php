<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketNotification extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $table = 'notifications';

    protected $fillable = [
        'id',
        'user_id',
        'title',
        'body',
        'type',
        'related_ticket_id',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) $model->id = (string) Str::uuid();
            if (empty($model->created_at)) $model->created_at = now();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'related_ticket_id');
    }
}
