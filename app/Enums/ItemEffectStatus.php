<?php

namespace App\Enums;

enum ItemEffectStatus: int
{
    case Pending = 1;
    case Applied = 2;
    case Blocked = 3;
    case Reflected = 4;
    case Missed = 5;
    case Expired = 6;
    case Consumed = 7;
    case Cancelled = 8;
}
