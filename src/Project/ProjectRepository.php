<?php

namespace Cinch\Project;

interface ProjectRepository
{
    /** Gets a project by name.
     * @param ProjectId $id
     * @return Project
     */
    public function get(ProjectId $id): Project;

    /** Creates a project.
     * @param Project $project
     * @return void
     */
    public function add(Project $project): void;

    public function remove(ProjectId $id): void;
}