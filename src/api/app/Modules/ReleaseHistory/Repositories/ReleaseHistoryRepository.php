<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Repositories;

use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;
use Illuminate\Database\Eloquent\Collection;

final class ReleaseHistoryRepository
{
    /**
     * Provision the single release history that backs a project. Invoked by the
     * Project module through ReleaseHistoryService when a project is created.
     */
    public function createForProject(string $projectId): ReleaseHistory
    {
        return ReleaseHistory::create(['project_id' => $projectId]);
    }

    public function latestNote(ReleaseHistory $history): ?ReleaseNote
    {
        return $history->releaseNotes()
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Cascade-delete the release history (and its release notes) that backs a
     * project. Invoked by the Project module through ReleaseHistoryService when
     * a project is deleted — both models use SoftDeletes, so these are soft
     * deletes consistent with the rest of the app.
     */
    public function deleteForProject(string $projectId): void
    {
        $history = ReleaseHistory::query()->where('project_id', $projectId)->first();

        if ($history === null)
        {
            return;
        }

        $history->releaseNotes()->delete();
        $history->delete();
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
