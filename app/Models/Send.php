<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Send extends Model
{
    use HasFactory;

    protected $table = 'send';
    protected $fillable = [
        'status',
        'message',
        'video',
        'photos',
        'forward_from_chat_id',
        'forward_from_message_id',
        'type',
        'last_count',
        'limit'
    ];
}
