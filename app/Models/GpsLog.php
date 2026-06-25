<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpsLog extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $fillable = ['officer_id','vehicle_id','schedule_id','lat','lng','transmitted_at'];
    protected $casts = ['transmitted_at' => 'datetime'];
    public function officer()  { return $this->belongsTo(User::class, 'officer_id'); }
    public function vehicle()  { return $this->belongsTo(Vehicle::class); }
    public function schedule() { return $this->belongsTo(Schedule::class); }
}
