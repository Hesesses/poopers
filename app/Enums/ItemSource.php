<?php

namespace App\Enums;

enum ItemSource: int
{
    case DailyWin = 1;
    case Draft = 2;
    case Milestone = 3;
    case Bonus = 4;
}
