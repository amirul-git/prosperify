<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipient extends Model
{
    use HasFactory;

    protected $fillable = ["nik", "name", "address", 'phone', 'family_members', 'photo', 'recipient_status_id'];
}
