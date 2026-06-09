<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Models;

use App\Models\User;
use Database\Factories\ReleaseNoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ReleaseNote extends Model
{
    /** @use HasFactory<ReleaseNoteFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'release_history_id',
        'author_id',
        'body',
        'version_major',
        'version_minor',
        'version_patch',
        'branch_name',
        'commithash_start',
        'commithash_end',
        'tag_start',
        'tag_end',
    ];

    public function releaseHistory(): BelongsTo
    {
        return $this->belongsTo(ReleaseHistory::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected static function newFactory(): ReleaseNoteFactory
    {
        return ReleaseNoteFactory::new();
    }

    protected function casts(): array
    {
        return [
            'version_major' => 'integer',
            'version_minor' => 'integer',
            'version_patch' => 'integer',
        ];
    }
}
