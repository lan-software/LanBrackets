<?php

namespace App\Domain\Competition\Formats\Swiss;

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
            'total_rounds' => null,
            'tiebreaker' => 'buchholz',
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

        if (isset($settings['total_rounds']) && $settings['total_rounds'] !== null) {
            if (! is_int($settings['total_rounds']) || $settings['total_rounds'] < 1) {
                $errors['total_rounds'] = 'total_rounds must be a positive integer or null.';
            }
        }

        if (isset($settings['tiebreaker'])) {
            if (! in_array($settings['tiebreaker'], ['buchholz'], true)) {
                $errors['tiebreaker'] = 'tiebreaker must be one of: buchholz.';
            }
        }

        return $errors;
    }

    public function apply(CompetitionStage $stage): void
    {
        $merged = array_merge($this->defaults(), $stage->settings ?? []);
        $stage->update(['settings' => $merged]);
    }
}
