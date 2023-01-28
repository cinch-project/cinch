<?php

namespace Cinch\Hook;

enum Event: string
{
    case AFTER_CONNECT = 'after-connect';
    case BEFORE_MIGRATE = 'before-migrate';
    case AFTER_MIGRATE = 'after-migrate';
    case BEFORE_ONCE_MIGRATE = 'before-once-migrate';
    case AFTER_ONCE_MIGRATE = 'after-once-migrate';
    case BEFORE_ALWAYS_MIGRATE = 'before-always-migrate';
    case AFTER_ALWAYS_MIGRATE = 'after-always-migrate';
    case BEFORE_ONCHANGE_MIGRATE = 'before-onchange-migrate';
    case AFTER_ONCHANGE_MIGRATE = 'after-onchange-migrate';
    case BEFORE_ROLLBACK = 'before-rollback';
    case AFTER_ROLLBACK = 'after-rollback';
    case BEFORE_EACH_ROLLBACK = 'before-each-rollback';
    case AFTER_EACH_ROLLBACK = 'after-each-rollback';

    public function isBefore(): bool
    {
        return str_starts_with($this->value, 'before-');
    }

    public function isAfter(): bool
    {
        return str_starts_with($this->value, 'after-');
    }
}
