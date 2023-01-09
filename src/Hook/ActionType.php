<?php

namespace Cinch\Hook;

enum ActionType: string
{
    /* SQL handler: executes sql after rendering template */
    case SQL = 'sql';
    /** PHP (embedded) handler: implements Handler interface */
    case PHP = 'php';
    /** Executable script (binary or shebang) */
    case SCRIPT = 'script';
    /** HTTP or HTTPS URL: always a POST with json body (hook 'arguments' are HTTP headers) */
    case HTTP = 'http';
}
