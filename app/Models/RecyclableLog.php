<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecyclableLog extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $fillable = ['officer_id','ward_id','schedule_id','category','quantity_kg','logged_at'];
    protected $casts = ['logged_at' => 'datetime'];
    public function officer()  { return $this->belongsTo(User::class, 'officer_id'); }
    public function ward()     { return $this->belongsTo(Ward::class); }
    public function schedule() { return $this->belongsTo(Schedule::class); }
}
