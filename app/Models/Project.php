<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $table = 'projects';
    protected $fillable = [
        'endpoint',
        'per_referral_amount',
        'per_vote_amount',
        'card_number',
        'phone_number',
        'password',
    ];
}
