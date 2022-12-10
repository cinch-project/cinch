<?php

namespace Cinch\History;

enum ChangeStatus: string
{
    case MIGRATED = 'migrated';
    case REMIGRATED = 'remigrated';
    case ROLLBACKED = 'rollbacked';
}
