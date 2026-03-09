<?php

namespace App\Services\Items\Contracts;

use App\Models\ItemEffect;
use App\Models\League;
use App\Models\User;
use App\Models\UserItem;

interface ItemHandlerInterface
{
    public function requiresTarget(): bool;

    public function allowsSelfTarget(): bool;

    public function validate(UserItem $userItem, User $user, ?User $target, League $league): void;

    public function execute(UserItem $userItem, User $user, ?User $target, League $league): ItemEffect;

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseData(ItemEffect $effect): ?array;

    public function hasMidnightResolution(): bool;

    public function resolveAtMidnight(ItemEffect $effect, League $league): void;

    public function bypassesDailyLimit(): bool;

    /**
     * @return array{title: string, body: string}|null
     */
    public function getTargetNotification(ItemEffect $effect, User $attacker): ?array;

    /**
     * @return array{title: string, body: string}|null
     */
    public function getUserNotification(ItemEffect $effect, ?User $target): ?array;
}
