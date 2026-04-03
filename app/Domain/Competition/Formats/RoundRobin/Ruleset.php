<?php

namespace App\Domain\Competition\Formats\RoundRobin;

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
            'points_win' => 3,
            'points_draw' => 1,
            'points_loss' => 0,
            'allow_draws' => true,
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

        foreach (['points_win', 'points_draw', 'points_loss'] as $field) {
            if (isset($settings[$field])) {
                if (! is_int($settings[$field]) || $settings[$field] < 0) {
                    $errors[$field] = "{$field} must be a non-negative integer.";
                }
            }
        }

        if (isset($settings['allow_draws']) && ! is_bool($settings['allow_draws'])) {
            $errors['allow_draws'] = 'allow_draws must be a boolean.';
        }

        return $errors;
    }

    public function apply(CompetitionStage $stage): void
    {
        $merged = array_merge($this->defaults(), $stage->settings ?? []);
        $stage->update(['settings' => $merged]);
    }
}
