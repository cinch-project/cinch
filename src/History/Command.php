<?php

namespace Cinch\History;

enum Command: string
{
    case MIGRATE = 'migrate';
    case ROLLBACK = 'rollback';
}