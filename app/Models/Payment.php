<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id','ward_id','billing_month','amount','status',
        'paid_at','payment_ref','notes','marked_by','property_type','registered_by',
    ];

    protected $casts = ['paid_at' => 'datetime', 'amount' => 'decimal:2'];

    public function resident()   { return $this->belongsTo(User::class, 'resident_id'); }
    public function ward()       { return $this->belongsTo(Ward::class); }
    public function markedBy()   { return $this->belongsTo(User::class, 'marked_by'); }
}
