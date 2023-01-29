<?php

namespace Cinch\Component\Schema\Check;

enum Operator: string
{
    case GT = '>';
    case GE = '>=';
    case LT = '<';
    case LE = '<=';
    case EQ = '=';
    case NE = '<>';
}
