<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vehicle extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $fillable = ['registration', 'make', 'model', 'is_active'];
    public function schedules() { return $this->hasMany(Schedule::class); }
    public function gpsLogs()   { return $this->hasMany(GpsLog::class); }
    public function latestGpsLog() { return $this->hasOne(GpsLog::class)->latestOfMany('transmitted_at'); }
}
