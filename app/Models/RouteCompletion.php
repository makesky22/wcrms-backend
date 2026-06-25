<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RouteCompletion extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $fillable = ['schedule_id','officer_id','ward_id','lat','lng','completed_at'];
    protected $casts = ['completed_at' => 'datetime'];
    public function schedule() { return $this->belongsTo(Schedule::class); }
    public function officer()  { return $this->belongsTo(User::class, 'officer_id'); }
    public function ward()     { return $this->belongsTo(Ward::class); }
}
