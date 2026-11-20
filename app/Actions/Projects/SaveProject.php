<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectSlugRedirect;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveProject
{
    /**
     * Persist validated project data, its status and relations atomically.
     *
     * @param  array<string, mixed>  $data  validated input incl. `status`, `skill_ids`, `tag_ids`
     */
    public function handle(Project $project, array $data, User $actor): Project
    {
        return DB::transaction(function () use ($project, $data, $actor): Project {
            $isNew = ! $project->exists;

            $project->fill(Arr::except($data, ['slug', 'status', 'skill_ids', 'tag_ids']));

            // An explicit slug wins; otherwise keep the existing one so public URLs stay stable.
            if (filled($data['slug'] ?? null)) {
                $project->slug = $data['slug'];
            } elseif ($isNew) {
                $project->slug = Project::uniqueSlug($data['title'], null);
            }

            ProjectStatus::from($data['status']) === ProjectStatus::Published
                ? $project->publish()
                : $project->unpublish();

            if ($isNew) {
                $project->sort_order = (int) Project::withTrashed()->max('sort_order') + 1;
            }

            $changed = array_keys($project->getDirty());
            $previousSlug = $project->getOriginal('slug');
            $project->save();

            if (! $isNew && is_string($previousSlug) && $previousSlug !== $project->slug) {
                $this->rememberOldSlug($project, $previousSlug);
            }

            if ($this->syncChanged($project->skills()->sync($data['skill_ids'] ?? []))) {
                $changed[] = 'skills';
            }

            if ($this->syncChanged($project->tags()->sync($data['tag_ids'] ?? []))) {
                $changed[] = 'tags';
            }

            if ($isNew || $changed !== []) {
                ActivityLog::record($actor, $isNew ? 'project.created' : 'project.updated', $project, [
                    'changed' => $isNew ? [] : array_values(array_diff($changed, ['updated_at'])),
                ]);
            }

            return $project;
        });
    }

    /**
     * Keep the old URL working; a slug that becomes current again stops redirecting.
     */
    private function rememberOldSlug(Project $project, string $previousSlug): void
    {
        ProjectSlugRedirect::where('slug', $project->slug)->delete();
        ProjectSlugRedirect::where('slug', $previousSlug)->delete();
        $project->slugRedirects()->create(['slug' => $previousSlug]);
    }

    /**
     * @param  array<string, array<int, mixed>>  $result
     */
    private function syncChanged(array $result): bool
    {
        return $result['attached'] !== [] || $result['detached'] !== [] || $result['updated'] !== [];
    }
}
