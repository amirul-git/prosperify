<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category_id', 'expiration_day_limit'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
