<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Recipient extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeEmails(Builder $query): Builder
    {
        return $query->whereNotNull('email')->where('email', '!=', '');
    }

    public function scopePhones(Builder $query): Builder
    {
        return $query->whereNotNull('phone')->where('phone', '!=', '');
    }
}
