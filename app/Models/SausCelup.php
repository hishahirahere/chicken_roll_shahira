<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SausCelup extends Model
{
    protected $table = 'saus_celup';

    protected $fillable = ['nama_saus', 'foto'];
}
