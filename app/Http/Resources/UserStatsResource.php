<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserStatsResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $resource
     */
    public function __construct(mixed $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'total_wins' => $this->resource['total_wins'],
            'total_losses' => $this->resource['total_losses'],
            'total_steps' => $this->resource['total_steps'],
            'leagues_count' => $this->resource['leagues_count'],
            'winning_streak_best' => $this->resource['winning_streak_best'],
            'not_losing_streak_best' => $this->resource['not_losing_streak_best'],
        ];
    }
}
