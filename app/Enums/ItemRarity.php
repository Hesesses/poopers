<?php

namespace App\Enums;

enum ItemRarity: int
{
    case Common = 1;
    case Uncommon = 2;
    case Rare = 3;
    case Epic = 4;
    case Legendary = 5;
}
