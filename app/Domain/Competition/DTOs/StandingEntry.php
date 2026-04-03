<?php

namespace App\Domain\Competition\DTOs;

final readonly class StandingEntry
{
    public function __construct(
        public int $participantId,
        public int $placement,
        public int $wins,
        public int $losses,
        public int $draws,
        public int $points,
        public float $tiebreaker,
        public ?int $groupId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'participant_id' => $this->participantId,
            'placement' => $this->placement,
            'wins' => $this->wins,
            'losses' => $this->losses,
            'draws' => $this->draws,
            'points' => $this->points,
            'tiebreaker' => $this->tiebreaker,
            'group_id' => $this->groupId,
        ];
    }
}
