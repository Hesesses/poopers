<?php

namespace App\Enums;

enum ItemEffectType: string
{
    case SpySingle = 'spy_single';
    case SpyInventory = 'spy_inventory';
    case BlockAttack = 'block_attack';
    case BlockAllAttacks = 'block_all_attacks';
    case ReduceSteps = 'reduce_steps';
    case BoostSteps = 'boost_steps';
    case ExposeSteps = 'expose_steps';
    case FakeSteps = 'fake_steps';
    case ReflectAttack = 'reflect_attack';
}
