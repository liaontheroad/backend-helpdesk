<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketAttachment extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id',
        'ticket_id',
        'file_url',
        'file_name',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) $model->id = (string) Str::uuid();
            if (empty($model->uploaded_at)) $model->uploaded_at = now();
        });
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
