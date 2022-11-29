<?php

namespace Cinch\Common;

enum CommitPolicy: string
{
    case ALWAYS = 'always';
    case ONCHANGE = 'onchange';
    case ONCE = 'once';
}
