<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Repositories;

use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;
use Illuminate\Database\Eloquent\Collection;

final class ReleaseHistoryRepository
{
    public function latestNote(ReleaseHistory $history): ?ReleaseNote
    {
        return $history->releaseNotes()
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return Collection<int, ReleaseNote>
     */
    public function orderedNotes(ReleaseHistory $history): Collection
    {
        return $history->releaseNotes()
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();
    }
}
