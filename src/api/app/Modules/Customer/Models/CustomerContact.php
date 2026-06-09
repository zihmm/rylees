<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use Database\Factories\CustomerContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CustomerContact extends Model
{
    /** @use HasFactory<CustomerContactFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'firstname',
        'lastname',
        'email',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function newFactory(): CustomerContactFactory
    {
        return CustomerContactFactory::new();
    }
}
