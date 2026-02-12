<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'type',
        'message',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
