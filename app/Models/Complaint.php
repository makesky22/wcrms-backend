<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'ward_id',           // ← This must be here
        'type',
        'description',
        'photo_path',
        'status',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function resident()
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}