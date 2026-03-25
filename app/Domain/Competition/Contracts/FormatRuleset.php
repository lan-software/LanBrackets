<?php

namespace App\Domain\Competition\Contracts;

use App\Models\CompetitionStage;

/**
 * Defines the ruleset configuration for a competition stage format.
 *
 * Rules may include: best-of-N series, map pools, walk-over policies,
 * points awarded per result, tiebreaker criteria, etc.
 *
 * TODO: Implement rulesets per competition type
 *   - TournamentRuleset (bracket rules, seeding strategy)
 *   - LeagueRuleset (points per win/draw/loss, tiebreakers, standings)
 *   - RaceRuleset (timing precision, heat configuration, DNS/DNF handling)
 */
interface FormatRuleset
{
    /**
     * Return the default settings for this format.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * Validate that the given stage settings are correct for this format.
     *
     * @param array<string, mixed> $settings
     * @return array<string, string> Validation errors keyed by field
     */
    public function validate(array $settings): array;

    /**
     * Apply this ruleset's configuration to a stage.
     */
    public function apply(CompetitionStage $stage): void;
}
