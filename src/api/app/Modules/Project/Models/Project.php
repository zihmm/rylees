<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use App\Models\Concerns\HasSlug;
use App\Models\LlmTemperatureType;
use App\Models\LlmTonalityType;
use App\Modules\Customer\Models\Customer;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasSlug, HasUuids, SoftDeletes;

    protected $guarded = ['token', 'key'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function releaseHistory(): HasOne
    {
        return $this->hasOne(ReleaseHistory::class);
    }

    public function tonality(): BelongsTo
    {
        return $this->belongsTo(LlmTonalityType::class, 'llm_tonality_id');
    }

    public function temperature(): BelongsTo
    {
        return $this->belongsTo(LlmTemperatureType::class, 'llm_temperature_id');
    }

    protected static function slugColumn(): string
    {
        return 'key';
    }

    protected static function slugScope(Builder $query, Model $model): Builder
    {
        return $query->where('customer_id', $model->customer_id);
    }

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }
}
