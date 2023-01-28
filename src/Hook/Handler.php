<?php

namespace Cinch\Hook;

use Exception;

interface Handler
{
    /**
     * @param Event $event
     * @param HandlerContext $context
     * @return int exitCode 0 for success, 1-255 for error
     * @throws Exception the exception's code is used as exitCode. if code is 0, 1 is used.
     */
    public function handle(Event $event, HandlerContext $context): int;
}
