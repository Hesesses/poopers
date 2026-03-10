<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Services\Items\Contracts\ItemHandlerInterface;
use App\Services\Items\Defensive\BidetShieldHandler;
use App\Services\Items\Defensive\DecoyDumpHandler;
use App\Services\Items\Defensive\GoldenThroneHandler;
use App\Services\Items\Defensive\HazmatSuitHandler;
use App\Services\Items\Defensive\OdorShieldHandler;
use App\Services\Items\Defensive\PlungerHandler;
use App\Services\Items\Defensive\ProbioticShieldHandler;
use App\Services\Items\Defensive\TitaniumToiletHandler;
use App\Services\Items\Defensive\WetWipeHandler;
use App\Services\Items\Offensive\CloggedPipesHandler;
use App\Services\Items\Offensive\CourtesyFlushHandler;
use App\Services\Items\Offensive\DiarrheaAttackHandler;
use App\Services\Items\Offensive\ExplosiveDiarrheaHandler;
use App\Services\Items\Offensive\PoopStreakHandler;
use App\Services\Items\Offensive\PortaPottyTrapHandler;
use App\Services\Items\Offensive\SepticTankHandler;
use App\Services\Items\Offensive\SewerBackupHandler;
use App\Services\Items\Offensive\SkidMarkHandler;
use App\Services\Items\Offensive\SplatterBombHandler;
use App\Services\Items\Offensive\StinkCloudHandler;
use App\Services\Items\Offensive\TheBrownOutHandler;
use App\Services\Items\Offensive\ToiletPaperTrailHandler;
use App\Services\Items\Offensive\UpperDeckerHandler;
use App\Services\Items\Strategic\AnonymousTipHandler;
use App\Services\Items\Strategic\CopycatHandler;
use App\Services\Items\Strategic\CrystalBallHandler;
use App\Services\Items\Strategic\DoubleOrNothingHandler;
use App\Services\Items\Strategic\FakePoopHandler;
use App\Services\Items\Strategic\FiberBoostHandler;
use App\Services\Items\Strategic\LaxativeHandler;
use App\Services\Items\Strategic\MorningCoffeeHandler;
use App\Services\Items\Strategic\PeekAPooHandler;
use App\Services\Items\Strategic\PruneJuiceHandler;
use App\Services\Items\Strategic\RoyalFlushHandler;
use App\Services\Items\Strategic\ScoutsScopeHandler;
use App\Services\Items\Strategic\TheInsiderHandler;
use App\Services\Items\Strategic\ToiletSwapHandler;
use RuntimeException;

class ItemHandlerRegistry
{
    /** @var array<string, class-string<ItemHandlerInterface>> */
    private const HANDLERS = [
        // Offensive
        'splatter_bomb' => SplatterBombHandler::class,
        'toilet_paper_trail' => ToiletPaperTrailHandler::class,
        'skid_mark' => SkidMarkHandler::class,
        'stink_cloud' => StinkCloudHandler::class,
        'clogged_pipes' => CloggedPipesHandler::class,
        'the_brown_out' => TheBrownOutHandler::class,
        'courtesy_flush' => CourtesyFlushHandler::class,
        'sewer_backup' => SewerBackupHandler::class,
        'diarrhea_attack' => DiarrheaAttackHandler::class,
        'porta_potty_trap' => PortaPottyTrapHandler::class,
        'upper_decker' => UpperDeckerHandler::class,
        'explosive_diarrhea' => ExplosiveDiarrheaHandler::class,
        'poop_streak' => PoopStreakHandler::class,
        'septic_tank' => SepticTankHandler::class,

        // Defensive
        'plunger' => PlungerHandler::class,
        'wet_wipe' => WetWipeHandler::class,
        'odor_shield' => OdorShieldHandler::class,
        'bidet_shield' => BidetShieldHandler::class,
        'decoy_dump' => DecoyDumpHandler::class,
        'hazmat_suit' => HazmatSuitHandler::class,
        'probiotic_shield' => ProbioticShieldHandler::class,
        'golden_throne' => GoldenThroneHandler::class,
        'titanium_toilet' => TitaniumToiletHandler::class,

        // Strategic
        'scouts_scope' => ScoutsScopeHandler::class,
        'fiber_boost' => FiberBoostHandler::class,
        'morning_coffee' => MorningCoffeeHandler::class,
        'peek_a_poo' => PeekAPooHandler::class,
        'fake_poop' => FakePoopHandler::class,
        'laxative' => LaxativeHandler::class,
        'copycat' => CopycatHandler::class,
        'anonymous_tip' => AnonymousTipHandler::class,
        'the_insider' => TheInsiderHandler::class,
        'double_or_nothing' => DoubleOrNothingHandler::class,
        'prune_juice' => PruneJuiceHandler::class,
        'crystal_ball' => CrystalBallHandler::class,
        'toilet_swap' => ToiletSwapHandler::class,
        'royal_flush' => RoyalFlushHandler::class,
    ];

    public function resolve(Item $item): ItemHandlerInterface
    {
        $handlerClass = self::HANDLERS[$item->slug] ?? null;

        if (! $handlerClass) {
            throw new RuntimeException("No handler registered for item slug: {$item->slug}");
        }

        return app($handlerClass);
    }

    public function has(string $slug): bool
    {
        return isset(self::HANDLERS[$slug]);
    }

    /**
     * @return array<string, class-string<ItemHandlerInterface>>
     */
    public function all(): array
    {
        return self::HANDLERS;
    }
}
