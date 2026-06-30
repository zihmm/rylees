<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Services;

use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;
use App\Modules\ReleaseHistory\Repositories\ReleaseHistoryRepository;

final class ReleaseHistoryService
{
    public function __construct(
        private readonly ReleaseHistoryRepository $repository,
    ) {}

    /**
     * Create the empty release history that backs a freshly created project.
     * Public entry point for the Project module (keeps release_histories writes
     * inside the owning module).
     */
    public function initialiseForProject(string $projectId): void
    {
        $this->repository->createForProject($projectId);
    }

    /**
     * Provision (and return) the release history that backs a project. Projects
     * created through the API always get one via initialiseForProject(), but
     * projects inserted directly by seeders/factories may not — callers use this
     * to lazily back-fill so publishing never fails on a null history.
     */
    public function provisionForProject(string $projectId): ReleaseHistory
    {
        return $this->repository->createForProject($projectId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function publish(ReleaseHistory $history, array $data, string $authorId): array
    {
        // Determine the version to build on: start from the most recent note's
        // version, or from 0.0.0 when this is the project's first release.
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

        // Apply the requested semantic-version bump. Incrementing a higher
        // component resets every lower one (e.g. minor bump zeroes the patch),
        // so 1.4.2 becomes 2.0.0 / 1.5.0 / 1.4.3 for major / minor / patch.
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
