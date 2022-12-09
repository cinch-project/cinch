<?php

namespace Cinch\Command;

enum RollbackType
{
    case TAG;
    case DATE;
    case COUNT;
    case SCRIPT;
}