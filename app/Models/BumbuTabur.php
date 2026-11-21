<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BumbuTabur extends Model
{
     protected $table = 'bumbu_tabur';

    protected $fillable = ['nama_bumbu', 'foto'];
}
