# LanBrackets Architecture

## Overview

LanBrackets is a Laravel-based tournament bracket management system. It generates and manages competition brackets for various tournament formats (single/double elimination, round robin, Swiss, group stage).

## Domain Model

```
Competition
├── CompetitionStage[]          (ordered stages, e.g. "Group Stage" → "Playoffs")
│   ├── CompetitionMatch[]      (matches within the stage)
│   │   ├── MatchParticipant[]  (2 per match: slot 1 & slot 2, with scores/results)
│   │   └── MatchConnection[]   (directed graph: source_match → target_match)
│   └── CompetitionStageGroup[] (for group-based formats)
│       └── CompetitionStageGroupMember[]
└── CompetitionParticipant[]    (polymorphic: Team or User)
    └── participant → Team | User (morph relation)
```

### Key Models

| Model | Table | Purpose |
|-------|-------|---------|
| `Competition` | `competitions` | Top-level entity with name, type, status, visibility |
| `CompetitionStage` | `competition_stages` | A phase of competition (e.g. "Main Bracket", "Group Stage") |
| `CompetitionMatch` | `matches` | A single match between 2 participants |
| `MatchParticipant` | `match_participants` | Links a participant to a match with slot, score, result |
| `MatchConnection` | `match_connections` | Bracket progression graph (winner/loser flows) |
| `CompetitionParticipant` | `competition_participants` | Registration of a Team/User in a competition |
| `CompetitionStageGroup` | `competition_stage_groups` | Groups within a group stage |
| `CompetitionStageGroupMember` | `competition_stage_group_members` | Participant membership in a group |

### Match Lifecycle

```
Pending → Scheduled → InProgress → Finished
                                 → Cancelled
```

Matches are created as `Pending`. When both participants have scores reported, the format's Resolver processes the match, setting it to `Finished`.

### Round Numbering Conventions

| Range | Meaning |
|-------|---------|
| 1–99 | Winners bracket / standard rounds |
| 100–199 | Losers bracket (double elimination) |
| 200+ | Grand final rounds (double elimination) |

## Strategy Pattern: Format System

Tournament formats are pluggable via three contracts:

### Contracts (`app/Domain/Competition/Contracts/`)

- **`FormatGenerator`** — `generate(CompetitionStage $stage): void`
  Creates the match structure, connections, and initial seeding for a stage.

- **`FormatResolver`** — `resolve(CompetitionMatch $match): void`
  Processes a completed match: determines winner/loser, advances participants through connections, and (for Swiss) generates next-round pairings.

- **`FormatRuleset`** — `defaults()`, `validate()`, `apply()`
  Defines and validates configurable settings for a format.

### FormatRegistry (`app/Domain/Competition/Services/FormatRegistry.php`)

Resolves Generator/Resolver/Ruleset instances from `config/competition-formats.php`. Uses Laravel's service container for dependency injection.

```php
$registry = app(FormatRegistry::class);
$generator = $registry->generator(StageType::SingleElimination);
$generator->generate($stage);
```

### Config (`config/competition-formats.php`)

Maps `StageType` enum values to their implementation classes:

```php
'single_elimination' => [
    'generator' => SingleElimination\Generator::class,
    'resolver' => SingleElimination\Resolver::class,
    'ruleset' => SingleElimination\Ruleset::class,
],
```

## Action Classes (`app/Actions/`)

Business logic is encapsulated in reusable Action classes:

| Action | Purpose |
|--------|---------|
| `CreateCompetitionAction` | Creates competition + initial stage with ruleset defaults |
| `GenerateBracketAction` | Validates participant count, delegates to FormatGenerator |
| `AddCompetitionParticipantAction` | Registers Team/User with auto-seed |
| `ReportMatchResultAction` | Updates scores, delegates to FormatResolver |
| `CreateParticipantAction` | Creates a Team or User entity |
| `AddTeamMemberAction` | Adds user to team with role |
| `RemoveTeamMemberAction` | Soft-removes team member |
| `AssignTeamCaptainAction` | Promotes member to captain |

These actions are format-agnostic — the FormatRegistry handles format-specific logic.

## Enums (`app/Enums/`)

| Enum | Values |
|------|--------|
| `CompetitionType` | Tournament, League, Race |
| `CompetitionStatus` | Draft, Planned, RegistrationOpen, RegistrationClosed, Running, Paused, Finished, Archived |
| `CompetitionVisibility` | Private, Unlisted, Public |
| `StageType` | GroupStage, SingleElimination, DoubleElimination, Swiss, RoundRobin, RaceHeat, FinalStage |
| `StageStatus` | Pending, Running, Completed |
| `MatchStatus` | Pending, Scheduled, InProgress, Finished, Cancelled |
| `MatchResult` | Win, Loss, Draw, Bye, Forfeit |
| `ParticipantStatus` | Registered, Confirmed, CheckedIn, Disqualified, Withdrawn |
| `ParticipantType` | Team, User |

## API

REST API under `/api/v1/` authenticated with bearer tokens. See `docs/api.md`.

## Frontend

Vue 3 + Inertia.js with canvas-based bracket visualization. Pages in `resources/js/pages/`.

## Console Commands

| Command | Purpose |
|---------|---------|
| `competition:create` | Interactive competition creation |
| `competition:generate-bracket` | Generate bracket for a competition |
| `competition:add-participant` | Add participant to competition |
| `competition:report-result` | Report match scores |
| `competition:list` | List all competitions |
| `competition:test-flow` | End-to-end test for any format |
| `tournament:tree` | ASCII bracket visualization |
| `db:seed-demo` | Seed demo data for all formats |
| `api-token:create` | Create API token |
| `api-token:list` | List API tokens |
| `api-token:revoke` | Revoke an API token |
