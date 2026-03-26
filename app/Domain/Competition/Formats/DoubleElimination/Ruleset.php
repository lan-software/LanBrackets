<?php

namespace App\Domain\Competition\Formats\DoubleElimination;

use App\Domain\Competition\Contracts\FormatRuleset;
use App\Models\CompetitionStage;

class Ruleset implements FormatRuleset
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'best_of' => 1,
            'grand_final_reset' => false,
            'third_place_match' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public function validate(array $settings): array
    {
        $errors = [];

        if (isset($settings['best_of'])) {
            $bestOf = $settings['best_of'];

            if (! is_int($bestOf) || $bestOf < 1 || $bestOf % 2 === 0) {
                $errors['best_of'] = 'best_of must be a positive odd integer.';
            }
        }

        if (isset($settings['grand_final_reset']) && ! is_bool($settings['grand_final_reset'])) {
            $errors['grand_final_reset'] = 'grand_final_reset must be a boolean.';
        }

        if (isset($settings['third_place_match']) && ! is_bool($settings['third_place_match'])) {
            $errors['third_place_match'] = 'third_place_match must be a boolean.';
        }

        return $errors;
    }

    public function apply(CompetitionStage $stage): void
    {
        $merged = array_merge($this->defaults(), $stage->settings ?? []);
        $stage->update(['settings' => $merged]);
    }
}
