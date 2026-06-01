<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Comment extends Model
{
    use HasFactory;

    public $incrementing  = false;
    protected $keyType    = 'string';
    public $timestamps    = false;

    protected $fillable = [
        'id',
        'ticket_id',
        'author_id',
        'author_name',
        'author_avatar_url',
        'author_role',
        'content',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function attachments()
    {
        return $this->hasMany(CommentAttachment::class, 'comment_id');
    }
}
