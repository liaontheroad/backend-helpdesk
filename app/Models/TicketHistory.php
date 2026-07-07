<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TicketHistory extends Model
{
    use HasUuids;

    protected $table = 'ticket_histories';

    protected $fillable = [
        'ticket_id',
        'status',
        'description',
        'performed_by',
    ];

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}