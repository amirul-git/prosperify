<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Rescue extends Model
{
    use HasFactory;

    protected $fillable = ['donor_name', 'pickup_address', 'phone', 'email', 'title', 'description', 'rescue_date', 'user_id', 'rescue_status_id'];

    public const PLANNED = 1;
    public const SUBMITTED = 2;
    public const PROCESSED = 3;
    public const ASSIGNED = 4;
    public const INCOMPLETED = 5;
    public const COMPLETED = 6;
    public const REJECTED = 7;
    public const FAILED = 8;

    protected function rescueDate(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Carbon::parse($value)->format('d M Y H:i')
        );
    }

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Carbon::parse($value)->format('d M Y H:i')
        );
    }

    public function foods()
    {
        return $this->hasMany(Food::class);
    }

    public function rescueStatus()
    {
        return $this->belongsTo(RescueStatus::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rescueAssignment()
    {
        return $this->hasMany(RescueAssignment::class);
    }
}
