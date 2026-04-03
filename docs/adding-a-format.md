# Adding a New Tournament Format

This guide walks through adding a new format to LanBrackets, using the existing strategy pattern.

## Step 1: Create the Format Classes

Create a new directory under `app/Domain/Competition/Formats/{FormatName}/` with three classes:

### Generator

Implements `App\Domain\Competition\Contracts\FormatGenerator`.

```php
<?php

namespace App\Domain\Competition\Formats\MyFormat;

use App\Domain\Competition\Contracts\FormatGenerator;
use App\Models\CompetitionStage;

class Generator implements FormatGenerator
{
    public function generate(CompetitionStage $stage): void
    {
        $participants = $stage->competition
            ->participants()
            ->whereNull('metadata->disqualified')
            ->orderBy('seed')
            ->get();

        // Create CompetitionMatch and MatchParticipant records
        // Optionally create MatchConnection records for bracket progression
    }
}
```

### Resolver

Implements `App\Domain\Competition\Contracts\FormatResolver`.

```php
<?php

namespace App\Domain\Competition\Formats\MyFormat;

use App\Domain\Competition\Contracts\FormatResolver;
use App\Models\CompetitionMatch;

class Resolver implements FormatResolver
{
    public function resolve(CompetitionMatch $match): void
    {
        // Determine winner/loser from scores
        // Update match status to Finished
        // Optionally advance participants through MatchConnections
    }
}
```

### Ruleset

Implements `App\Domain\Competition\Contracts\FormatRuleset`.

```php
<?php

namespace App\Domain\Competition\Formats\MyFormat;

use App\Domain\Competition\Contracts\FormatRuleset;
use App\Models\CompetitionStage;

class Ruleset implements FormatRuleset
{
    public function defaults(): array
    {
        return ['best_of' => 1];
    }

    public function validate(array $settings): array
    {
        $errors = [];
        // Validate settings, return keyed error messages
        return $errors;
    }

    public function apply(CompetitionStage $stage): void
    {
        $merged = array_merge($this->defaults(), $stage->settings ?? []);
        $stage->update(['settings' => $merged]);
    }
}
```

## Step 2: Register in Config

Add the format to `config/competition-formats.php`:

```php
'my_format' => [
    'generator' => \App\Domain\Competition\Formats\MyFormat\Generator::class,
    'resolver' => \App\Domain\Competition\Formats\MyFormat\Resolver::class,
    'ruleset' => \App\Domain\Competition\Formats\MyFormat\Ruleset::class,
],
```

## Step 3: Update Minimum Participants

In `app/Actions/GenerateBracketAction.php`, add your format to `minimumParticipants()`:

```php
return match ($stage->stage_type->value) {
    'single_elimination' => 2,
    'double_elimination' => 3,
    'round_robin' => 2,
    'swiss' => 4,
    'group_stage' => 4,
    'my_format' => 2,  // Add this
    default => 2,
};
```

## Step 4: Add Factory State

In `database/factories/CompetitionStageFactory.php`:

```php
public function myFormat(): static
{
    return $this->state(fn (array $attributes) => [
        'stage_type' => StageType::MyFormat,
    ]);
}
```

Note: You also need to add the enum case to `app/Enums/StageType.php` if it doesn't exist yet.

## Step 5: Write Tests

Create `tests/Feature/MyFormatTest.php` following the pattern from `SingleEliminationTest.php`:

- Helper function to create stage with participants
- Generator tests: correct match count, participant assignment, edge cases
- Resolver tests: winner/loser determination, advancement
- Ruleset tests: defaults, validation, apply
- FormatRegistry integration test
- Full playthrough test

## Step 6: Update SeedDemo (Optional)

Add a new format seed to `app/Console/Commands/SeedDemo.php` in the `handle()` method.

## Step 7: Run Pint

```bash
vendor/bin/sail bin pint --dirty --format agent
```

## Key Patterns to Follow

- **BYE handling**: Use `MatchStatus::Finished` + `MatchResult::Bye` for auto-advanced matches
- **MatchConnection**: Only needed for bracket-progression formats (elimination). Not needed for round-robin, Swiss, or group stage.
- **Draw support**: Use `allow_draws` setting + `MatchResult::Draw` for formats that support ties.
- **Reuse**: The `RoundRobin\Scheduler` can be injected for any format that needs round-robin scheduling within groups.
