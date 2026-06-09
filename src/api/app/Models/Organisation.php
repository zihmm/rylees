<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Modules\Customer\Models\Customer;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory, HasSlug, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'street',
        'postcode',
        'city',
        'website',
        'email',
    ];

    public function userProfiles(): HasMany
    {
        return $this->hasMany(UserProfile::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    protected static function newFactory(): OrganisationFactory
    {
        return OrganisationFactory::new();
    }
}
