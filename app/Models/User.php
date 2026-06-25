<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name','email','phone','password','role',
        'ward_id','is_active','property_type',
    ];
    protected $hidden = ['password','remember_token'];
    protected $casts  = ['email_verified_at' => 'datetime', 'password' => 'hashed'];

    public function ward()                { return $this->belongsTo(Ward::class); }
    public function schedules()           { return $this->hasMany(Schedule::class, 'officer_id'); }
    public function supervisedSchedules() { return $this->hasMany(Schedule::class, 'supervisor_id'); }
    public function gpsLogs()             { return $this->hasMany(GpsLog::class, 'officer_id'); }
    public function complaints()          { return $this->hasMany(Complaint::class, 'resident_id'); }
    public function recyclableLogs()      { return $this->hasMany(RecyclableLog::class, 'officer_id'); }
    public function notifications()       { return $this->hasMany(Notification::class); }
    public function payments()            { return $this->hasMany(Payment::class, 'resident_id'); }

    public function isSupervisor(): bool  { return $this->role === 'supervisor'; }
    public function isOfficer(): bool     { return $this->role === 'officer'; }
    public function isResident(): bool    { return $this->role === 'resident'; }
    public function isAdmin(): bool       { return $this->role === 'admin'; }
    public function isRegistrar(): bool   { return $this->role === 'registrar'; }

    // Property billing rates
    public static function propertyRates(): array {
        return [
            'residential' => ['amount' => 2000,  'cycle' => 'monthly', 'label' => 'Nyumba ya Makazi'],
            'shop'        => ['amount' => 5000,  'cycle' => 'monthly', 'label' => 'Duka'],
            'market'      => ['amount' => 50000, 'cycle' => 'daily',   'label' => 'Soko'],
        ];
    }
}
