# Tournament Formats

## Single Elimination

**Location:** `app/Domain/Competition/Formats/SingleElimination/`

Standard knockout bracket. Lose once and you're out.

### Generator
- Creates power-of-2 bracket with BYE handling for non-power-of-2 counts
- Standard tournament seeding: top seeds maximally separated (1 vs N can only meet in final)
- BYE matches auto-finish with `MatchResult::Bye`
- Optional 3rd place match between semifinal losers

### Resolver
- Determines winner from scores (higher score wins)
- Does **not** allow draws
- Advances winner through `MatchConnection` graph (`source_outcome: 'winner'`)
- Advances loser through loser connections (for 3rd place match)

### Ruleset Defaults
```php
['best_of' => 1, 'third_place_match' => false]
```

### Minimum Participants: 2

---

## Double Elimination

**Location:** `app/Domain/Competition/Formats/DoubleElimination/`

Two-loss elimination. Winners bracket feeds losers to the losers bracket.

### Generator
- Winners bracket: standard single-elimination structure
- Losers bracket: 2× rounds (odd rounds receive dropdowns from WB, even rounds are internal LB)
- Grand final: WB champion vs LB champion
- Optional grand final reset (LB champion must beat WB champion twice)
- Optional 3rd place match
- Losers bracket rounds use 100+ numbering, grand final uses 200+

### Resolver
- Same win/loss determination as single elimination
- Advances winners/losers through connections
- Special grand final reset logic: if WB champion loses GF, generates reset match; if WB champion wins, cancels pre-generated reset match

### Ruleset Defaults
```php
['best_of' => 1, 'grand_final_reset' => false, 'third_place_match' => false]
```

### Minimum Participants: 3

---

## Round Robin

**Location:** `app/Domain/Competition/Formats/RoundRobin/`

Every participant plays every other participant exactly once.

### Generator
- Uses the **circle method algorithm** via `Scheduler` class
- For N participants: N-1 rounds (even) or N rounds (odd, with BYE rotation)
- All matches created upfront — no bracket progression
- No `MatchConnection` records (matches are independent)

### Scheduler (`RoundRobin/Scheduler.php`)
Shared service used by both RoundRobin and GroupStage generators. Takes a collection of participants and creates all round-robin matches.

### Resolver
- Supports **draws** when `allow_draws` is true (default)
- When scores are tied: both participants get `MatchResult::Draw`, no winner/loser set
- No advancement through connections — standings computed at query time

### Ruleset Defaults
```php
['best_of' => 1, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0, 'allow_draws' => true]
```

### Minimum Participants: 2

---

## Swiss

**Location:** `app/Domain/Competition/Formats/Swiss/`

Fixed number of rounds with dynamic pairing. No elimination.

### Generator
- Generates **round 1 only** — subsequent rounds generated dynamically by the Resolver
- Round 1 pairing: by seed (1v2, 3v4, etc.)
- Total rounds auto-calculated as `ceil(log2(N))` or configurable
- BYE for odd participant counts (lowest-seeded gets BYE)

### Resolver (most complex)
After resolving a match, checks if all current-round matches are done. If so:
1. Calculates standings: wins, then **Buchholz tiebreaker** (sum of opponents' wins)
2. Pairs participants with matching records, avoiding repeat matchups (greedy top-down)
3. BYE rotation: prefers participant who hasn't had a BYE yet
4. Creates next round's matches

### Key Design: Dynamic Round Generation
Unlike other formats, Swiss generates rounds incrementally. The Resolver both resolves match outcomes AND generates next-round pairings when a round completes. No `MatchConnection` records are used.

### Ruleset Defaults
```php
['best_of' => 1, 'total_rounds' => null, 'tiebreaker' => 'buchholz']
```

### Minimum Participants: 4

---

## Group Stage

**Location:** `app/Domain/Competition/Formats/GroupStage/`

Divides participants into groups, runs round robin within each group.

### Generator
- Divides participants using **serpentine seeding** (1,8,9,16 in Group A; 2,7,10,15 in Group B)
- Creates `CompetitionStageGroup` and `CompetitionStageGroupMember` records
- **Reuses `RoundRobin\Scheduler`** for match generation within each group
- Tags matches with `settings['group_id']`

### Resolver
- Delegates to `RoundRobin\Resolver` — same draw support, no advancement
- Standings computed at query time from `MatchParticipant` results

### Ruleset Defaults
```php
[
    'best_of' => 1,
    'group_count' => null,   // auto: ceil(N / group_size)
    'group_size' => 4,
    'points_win' => 3,
    'points_draw' => 1,
    'points_loss' => 0,
    'allow_draws' => true,
    'advance_count' => 2,    // top N per group advance
]
```

### Minimum Participants: 4

---

## Not Yet Implemented

- **RaceHeat** — Timed race heats
- **FinalStage** — Post-group playoff bracket
