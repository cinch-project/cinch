<?php

namespace Cinch\Project;

enum HookEvent: string
{
    case BEFORE_CONNECT = 'before_connect';
    case AFTER_CONNECT = 'after_connect';
    case BEFORE_COMMIT = 'before_commit';
    case AFTER_COMMIT = 'after_commit';
    case BEFORE_EACH_COMMIT = 'before_each_commit';
    case AFTER_EACH_COMMIT = 'after_each_commit';
    case BEFORE_REVERT = 'before_revert';
    case AFTER_REVERT = 'after_revert';
    case BEFORE_EACH_REVERT = 'before_each_revert';
    case AFTER_EACH_REVERT = 'after_each_revert';
}