<?php

namespace Cinch\History;

enum Status: string
{
    case MIGRATED = 'migrated';
    case REMIGRATED = 'remigrated';
    case ROLLBACKED = 'rollbacked';
}
