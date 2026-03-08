<?php

namespace Database\Seeders;

use App\Enums\ItemRarity;
use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Offensive items (4)
            [
                'slug' => 'splatter-bomb',
                'name' => 'Splatter Bomb',
                'description' => 'Reduce a player\'s steps by 10%',
                'type' => ItemType::Offensive,
                'rarity' => ItemRarity::Common,
                'effect' => ['type' => 'reduce_steps', 'value' => 10, 'unit' => 'percent'],
                'icon' => '💣',
            ],
            [
                'slug' => 'diarrhea-attack',
                'name' => 'Diarrhea Attack',
                'description' => 'Reduce a player\'s steps by 20%',
                'type' => ItemType::Offensive,
                'rarity' => ItemRarity::Uncommon,
                'effect' => ['type' => 'reduce_steps', 'value' => 20, 'unit' => 'percent'],
                'icon' => '💩',
            ],
            [
                'slug' => 'upper-decker',
                'name' => 'Upper Decker',
                'description' => 'Reduce a player\'s steps by 30%',
                'type' => ItemType::Offensive,
                'rarity' => ItemRarity::Rare,
                'effect' => ['type' => 'reduce_steps', 'value' => 30, 'unit' => 'percent'],
                'icon' => '🚽',
            ],
            [
                'slug' => 'clogged-toilet',
                'name' => 'Clogged Toilet',
                'description' => 'Reduce a player\'s steps by 50%',
                'type' => ItemType::Offensive,
                'rarity' => ItemRarity::Epic,
                'effect' => ['type' => 'reduce_steps', 'value' => 50, 'unit' => 'percent'],
                'icon' => '🪠',
            ],
            // Defensive items (3)
            [
                'slug' => 'air-freshener',
                'name' => 'Air Freshener',
                'description' => 'Block one incoming attack today',
                'type' => ItemType::Defensive,
                'rarity' => ItemRarity::Common,
                'effect' => ['type' => 'block_attack', 'value' => 1, 'unit' => 'count'],
                'icon' => '🌸',
            ],
            [
                'slug' => 'bidet-shield',
                'name' => 'Bidet Shield',
                'description' => 'Reflect one incoming attack back to the attacker',
                'type' => ItemType::Defensive,
                'rarity' => ItemRarity::Rare,
                'effect' => ['type' => 'reflect_attack', 'value' => 1, 'unit' => 'count'],
                'icon' => '🛡️',
            ],
            [
                'slug' => 'hazmat-suit',
                'name' => 'Hazmat Suit',
                'description' => 'Block all incoming attacks today',
                'type' => ItemType::Defensive,
                'rarity' => ItemRarity::Epic,
                'effect' => ['type' => 'block_all_attacks', 'value' => 1, 'unit' => 'day'],
                'icon' => '🦺',
            ],
            // Strategic items (5)
            [
                'slug' => 'scouts-scope',
                'name' => 'Scout\'s Scope',
                'description' => 'See one player\'s current steps',
                'type' => ItemType::Strategic,
                'rarity' => ItemRarity::Common,
                'effect' => ['type' => 'spy_single', 'value' => 1, 'unit' => 'player'],
                'icon' => '🔭',
            ],
            [
                'slug' => 'the-insider',
                'name' => 'The Insider',
                'description' => 'See everyone\'s inventory in the league',
                'type' => ItemType::Strategic,
                'rarity' => ItemRarity::Uncommon,
                'effect' => ['type' => 'spy_inventory', 'value' => 1, 'unit' => 'league'],
                'icon' => '🕵️',
            ],
            [
                'slug' => 'the-reveal',
                'name' => 'The Reveal',
                'description' => 'Expose someone\'s steps to the whole group',
                'type' => ItemType::Strategic,
                'rarity' => ItemRarity::Uncommon,
                'effect' => ['type' => 'reveal_steps', 'value' => 1, 'unit' => 'player'],
                'icon' => '📢',
            ],
            [
                'slug' => 'fake-poop',
                'name' => 'Fake Poop',
                'description' => 'Show a fake step count to spy items',
                'type' => ItemType::Strategic,
                'rarity' => ItemRarity::Rare,
                'effect' => ['type' => 'fake_steps', 'value' => 1, 'unit' => 'day'],
                'icon' => '🎭',
            ],
            [
                'slug' => 'step-boost',
                'name' => 'Step Boost',
                'description' => 'Add 10% bonus to your steps today',
                'type' => ItemType::Strategic,
                'rarity' => ItemRarity::Uncommon,
                'effect' => ['type' => 'boost_steps', 'value' => 10, 'unit' => 'percent'],
                'icon' => '⚡',
            ],
        ];

        foreach ($items as $item) {
            Item::query()->updateOrCreate(
                ['slug' => $item['slug']],
                $item,
            );
        }
    }
}
