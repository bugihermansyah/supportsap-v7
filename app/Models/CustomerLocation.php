<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLocation extends Model
{
    protected $fillable = [
        'customer_id',
        'location_id',
        'is_to',
        'is_contact_shipping',
    ];

    protected $casts = [
        'is_to' => 'boolean',
        'is_contact_shipping' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
