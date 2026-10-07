<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A second identity model. It exists to prove that an identity of one type
 * never reaches a view owned by an identity of another type that happens to
 * share its primary key.
 */
class Admin extends Authenticatable
{
    use HasFactory;

    protected $table = 'admins';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected static function newFactory(): AdminFactory
    {
        return AdminFactory::new();
    }
}
