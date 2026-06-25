<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Schedule extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $fillable = ['ward_id','vehicle_id','officer_id','supervisor_id','collection_days','start_time','end_time','status','notes'];
    public function ward()        { return $this->belongsTo(Ward::class); }
    public function vehicle()     { return $this->belongsTo(Vehicle::class); }
    public function officer()     { return $this->belongsTo(User::class, 'officer_id'); }
    public function supervisor()  { return $this->belongsTo(User::class, 'supervisor_id'); }
    public function completions() { return $this->hasMany(RouteCompletion::class); }
    public function gpsLogs()     { return $this->hasMany(GpsLog::class); }
    public function recyclableLogs(){ return $this->hasMany(RecyclableLog::class); }
}
