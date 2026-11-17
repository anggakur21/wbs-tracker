<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An implementation partner company. Its people sign in through their own user accounts. */
class Partner extends Model
{
    protected $fillable = ['name', 'contact_email', 'phone'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class)->orderBy('name');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('name');
    }
}
