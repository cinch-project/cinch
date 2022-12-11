<?php

namespace Cinch\Common;

enum MigratePolicy: string
{
    case ALWAYS = 'always';
    case ONCHANGE = 'onchange';
    case ONCE = 'once';
}
