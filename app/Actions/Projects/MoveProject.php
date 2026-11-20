<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Support\SortOrder;

class MoveProject
{
    /**
     * Move a project one position up or down in the global public order.
     */
    public function handle(Project $project, string $direction): void
    {
        SortOrder::move(Project::query()->ordered(), $project, $direction);
    }
}
