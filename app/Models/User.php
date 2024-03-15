<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $table = 'users';
    protected $fillable = [
        'chat_id',
        'full_name',
        'username',
        'balance',
        'vote_count',
        'referrer_id',
        'phone_number',
    ];


    // Referrer relationship
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }
}
