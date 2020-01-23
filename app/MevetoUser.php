<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MevetoUser extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'id',
        'last_logged_in',
        'last_logged_out',
        'is_logged_in'
    ];
}
