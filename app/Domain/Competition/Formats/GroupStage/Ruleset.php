<?php

namespace App\Domain\Competition\Formats\GroupStage;

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
            'group_count' => null,
            'group_size' => 4,
            'points_win' => 3,
            'points_draw' => 1,
            'points_loss' => 0,
            'allow_draws' => true,
            'advance_count' => 2,
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

        if (isset($settings['group_count']) && $settings['group_count'] !== null) {
            if (! is_int($settings['group_count']) || $settings['group_count'] < 2) {
                $errors['group_count'] = 'group_count must be at least 2 or null for auto-calculation.';
            }
        }

        if (isset($settings['group_size'])) {
            if (! is_int($settings['group_size']) || $settings['group_size'] < 2) {
                $errors['group_size'] = 'group_size must be at least 2.';
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

        if (isset($settings['advance_count'])) {
            if (! is_int($settings['advance_count']) || $settings['advance_count'] < 1) {
                $errors['advance_count'] = 'advance_count must be a positive integer.';
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
