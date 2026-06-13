<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Models;

use App\Modules\Project\Models\Project;
use Database\Factories\ReleaseHistoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ReleaseHistory extends Model
{
    /** @use HasFactory<ReleaseHistoryFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'project_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function releaseNotes(): HasMany
    {
        return $this->hasMany(ReleaseNote::class);
    }

    public function latestReleaseNote(): HasOne
    {
        // Ordered hasOne rather than latestOfMany(): the ofMany aggregate adds the
        // UUID primary key as a MAX() tiebreaker, and PostgreSQL has no max(uuid).
        // On eager load Laravel keeps the first matched row per parent, so the
        // descending order yields the latest note; on lazy load it becomes LIMIT 1.
        return $this->hasOne(ReleaseNote::class)->orderByDesc('created_at');
    }

    protected static function newFactory(): ReleaseHistoryFactory
    {
        return ReleaseHistoryFactory::new();
    }
}
