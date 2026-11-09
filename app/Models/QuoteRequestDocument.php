<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteRequestDocument extends Model
{
    protected $fillable = ['path', 'name', 'size', 'mime'];
}
