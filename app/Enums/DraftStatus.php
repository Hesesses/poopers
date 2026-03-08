<?php

namespace App\Enums;

enum DraftStatus: int
{
    case Pending = 1;
    case InProgress = 2;
    case Completed = 3;
}
