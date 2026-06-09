<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Services;

use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Models\ReleaseNote;
use App\Modules\ReleaseHistory\Repositories\ReleaseHistoryRepository;

final class ReleaseHistoryService
{
    public function __construct(
        private readonly ReleaseHistoryRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function publish(Project $project, array $data, string $authorId): array
    {
        $history = $project->releaseHistory;

        $latest = $this->repository->latestNote($history);

        if ($latest === null)
        {
            $major = 0;
            $minor = 0;
            $patch = 0;
        } else
        {
            $major = $latest->version_major;
            $minor = $latest->version_minor;
            $patch = $latest->version_patch;
        }

        switch ($data['versionBump'])
        {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
                $patch++;
                break;
        }

        $type = $data['type'];

        $note = new ReleaseNote([
            'release_history_id' => $history->id,
            'author_id' => $authorId,
            'body' => $data['body'],
            'version_major' => $major,
            'version_minor' => $minor,
            'version_patch' => $patch,
            'branch_name' => $data['branchName'] ?? null,
            'commithash_start' => $type === 'commits' ? $data['startRef'] : null,
            'commithash_end' => $type === 'commits' ? $data['endRef'] : null,
            'tag_start' => $type === 'tag' ? $data['startRef'] : null,
            'tag_end' => $type === 'tag' ? $data['endRef'] : null,
        ]);

        $note->save();

        return [
            'id' => $note->id,
            'status' => 'published',
            'version' => "{$major}.{$minor}.{$patch}",
        ];
    }
}
