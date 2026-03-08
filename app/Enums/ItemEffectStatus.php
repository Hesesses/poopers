<?php

namespace App\Enums;

enum ItemEffectStatus: int
{
    case Pending = 1;
    case Applied = 2;
    case Blocked = 3;
    case Reflected = 4;
}
