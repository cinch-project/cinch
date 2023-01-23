<?php

namespace Cinch\Project;

interface ProjectRepository
{
    /** Gets a project.
     * @param ProjectName $name
     * @return Project
     */
    public function get(ProjectName $name): Project;

    /** Adds a project.
     * @param Project $project
     */
    public function add(Project $project): void;

    /** Updates a project.
     * @param Project $project
     */
    public function update(Project $project): void;

    /** Removes a project.
     * @param ProjectName $name
     */
    public function remove(ProjectName $name): void;
}