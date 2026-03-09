<?php

namespace App\Services\Items;

use App\Models\ItemEffect;

class DefenseResult
{
    public bool $blocked = false;

    public bool $reflected = false;

    public bool $missed = false;

    public float $damageMultiplier = 1.0;

    public ?int $blockedByItemId = null;

    /** @var array<int, ItemEffect> */
    public array $reactiveEffects = [];
}
