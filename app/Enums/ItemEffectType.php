<?php

namespace App\Enums;

enum ItemEffectType: string
{
    case SpySingle = 'spy_single';
    case SpyInventory = 'spy_inventory';
    case SpySelfRank = 'spy_self_rank';
    case BlockAllAttacks = 'block_all_attacks';
    case BlockAllAndBonus = 'block_all_and_bonus';
    case ReduceSteps = 'reduce_steps';
    case BoostSteps = 'boost_steps';
    case ExposeSteps = 'expose_steps';
    case FakeSteps = 'fake_steps';
    case ReflectAttack = 'reflect_attack';
    case StealSteps = 'steal_steps';
    case BlockItems = 'block_items';
    case HideRanking = 'hide_ranking';
    case RemoveDefense = 'remove_defense';
    case ReverseBuff = 'reverse_buff';
    case SwapPlacement = 'swap_placement';
    case SetTrap = 'set_trap';
    case DoubleStreak = 'double_streak';
    case ScalingReduction = 'scaling_reduction';
    case SplashDamage = 'splash_damage';
    case RemoveEffect = 'remove_effect';
    case ReduceDamage = 'reduce_damage';
    case HideStepsFromAttacker = 'hide_steps_from_attacker';
    case Dodge = 'dodge';
    case ReactiveBonus = 'reactive_bonus';
    case NegateAndBonus = 'negate_and_bonus';
    case TimedBoost = 'timed_boost';
    case ForceReveal = 'force_reveal';
    case CopyItem = 'copy_item';
    case AnonymousMode = 'anonymous_mode';
    case CoinFlip = 'coin_flip';
    case ForceUse = 'force_use';
    case Projection = 'projection';
    case StealItem = 'steal_item';
    case SwapFirstLast = 'swap_first_last';
}
