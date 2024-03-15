<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;

    protected $table = 'votes';
    protected $fillable = [
        'user_id',
        'project_id',
        'status',
        'phone_number'
    ];


    // project relation
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    // user relation
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
