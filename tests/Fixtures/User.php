<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
