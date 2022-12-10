<?php

namespace Cinch\History;

enum DeploymentCommand: string
{
    case MIGRATE = 'migrate';
    case ROLLBACK = 'rollback';
}