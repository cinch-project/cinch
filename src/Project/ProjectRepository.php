<?php

namespace Cinch\Project;

interface ProjectRepository
{
    /** Gets a project.
     * @param ProjectId $id
     * @return Project
     */
    public function get(ProjectId $id): Project;

    /** Adds a project.
     * @param Project $project
     */
    public function add(Project $project): void;

    /** Updates a project.
     * @param Project $project
     */
    public function update(Project $project): void;

    /** Removes a project.
     * @param ProjectId $id
     */
    public function remove(ProjectId $id): void;
}