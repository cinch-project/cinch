<?php

namespace Cinch\Hook;

enum ActionType: string
{
    /* SQL script: sent to target after being rendered */
    case SQL = 'sql';
    /** PHP handler: implements Cinch\Hook\Handler */
    case PHP = 'php';
    /** Executable script (binary or shebang) */
    case SCRIPT = 'script';
    /** HTTP or HTTPS URL: always a POST with json body */
    case HTTP = 'http';
}
