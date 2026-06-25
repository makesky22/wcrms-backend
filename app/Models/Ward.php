<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ward extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $fillable = ['name', 'code', 'lat', 'lng', 'is_active'];
    public function residents()  { return $this->hasMany(User::class)->where('role', 'resident'); }
    public function schedules()  { return $this->hasMany(Schedule::class); }
    public function complaints() { return $this->hasMany(Complaint::class); }
    public function recyclableLogs() { return $this->hasMany(RecyclableLog::class); }
}
