<?php

namespace Cinch\History;

enum Command: string
{
    case COMMIT = 'commit';
    case REVERT = 'revert';
}