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

    protected static function newFactory(): ReleaseHistoryFactory
    {
        return ReleaseHistoryFactory::new();
    }
}
