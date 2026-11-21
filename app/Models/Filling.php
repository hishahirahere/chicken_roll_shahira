<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Filling extends Model
{
    protected $table = 'filling';

    protected $fillable = ['nama_filling', 'foto'];
}
