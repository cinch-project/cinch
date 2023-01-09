<?php

namespace Cinch\Hook;

enum Event: string
{
    case BEFORE_CONNECT = 'before-connect';
    case AFTER_CONNECT = 'after-connect';
    case BEFORE_MIGRATE = 'before-migrate';
    case AFTER_MIGRATE = 'after-migrate';
    case BEFORE_EACH_MIGRATE = 'before-each-migrate';
    case AFTER_EACH_MIGRATE = 'after-each-migrate';
    case BEFORE_ROLLBACK = 'before-rollback';
    case AFTER_ROLLBACK = 'after-rollback';
    case BEFORE_EACH_ROLLBACK = 'before-each-rollback';
    case AFTER_EACH_ROLLBACK = 'after-each-rollback';
}