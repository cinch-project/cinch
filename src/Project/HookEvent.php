<?php

namespace Cinch\Project;

enum HookEvent: string
{
    case BEFORE_CONNECT = 'before_connect';
    case AFTER_CONNECT = 'after_connect';
    case BEFORE_MIGRATE = 'before_migrate';
    case AFTER_MIGRATE = 'after_migrate';
    case BEFORE_EACH_MIGRATE = 'before_each_migrate';
    case AFTER_EACH_MIGRATE = 'after_each_migrate';
    case BEFORE_ROLLBACK = 'before_rollback';
    case AFTER_ROLLBACK = 'after_rollback';
    case BEFORE_EACH_ROLLBACK = 'before_each_rollback';
    case AFTER_EACH_ROLLBACK = 'after_each_rollback';
}