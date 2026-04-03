# LanCore Competition Feature Domain — Bootstrap Prompt

> Copy everything below the line into a Claude Code conversation in the LanCore project.

---

## Context

LanCore is the main user-facing LAN event management platform. We are bootstrapping a **Competition feature domain** that integrates with **LanBrackets** — a separate microservice that handles bracket generation, match trees, and live bracket visualization.

**Architecture:**
- LanCore owns: competition creation UI, user registration, admin management, match result reporting UI
- LanBrackets owns: bracket math (single/double elimination, Swiss, round robin, group stage), match tree generation, OBS overlay visualization
- LanCore talks to LanBrackets via REST API (bearer token auth)
- LanBrackets talks back to LanCore via webhooks (HMAC-signed)

LanCore already has an **Integration domain** (with models, services, integration app management) and a **Webhook domain** (with webhook receiving infrastructure). The Competition domain should integrate with both.

## What to build

Bootstrap the Competition feature domain in LanCore with the following:

### 1. LanBrackets API Client Service

Create a service class (e.g., `app/Domain/Competition/Services/LanBracketsClient.php`) that wraps all LanBrackets API calls using Laravel's `Http` facade. The service should:

- Accept a base URL and bearer token (from the LanBrackets integration config)
- Provide typed methods for each API endpoint (listed below)
- Return structured DTOs or arrays, not raw responses
- Handle errors gracefully (throw domain-specific exceptions)

**LanBrackets API v1 endpoints** (all require `Authorization: Bearer lbt_...`):

```
# Competition CRUD
POST   /api/v1/competitions                                    — Create competition
GET    /api/v1/competitions                                    — List (supports ?external_reference_id=&source_system= filtering)
GET    /api/v1/competitions/{id}                               — Show with stages & participants
PUT    /api/v1/competitions/{id}                               — Update competition
DELETE /api/v1/competitions/{id}                               — Delete (only draft/archived)

# Participants
POST   /api/v1/competitions/{id}/participants                  — Add single participant
POST   /api/v1/competitions/{id}/participants/bulk             — Add participants in bulk (transactional)
DELETE /api/v1/competitions/{id}/participants/{participantId}   — Withdraw (before bracket gen only)
POST   /api/v1/competitions/{id}/participants/{participantId}/disqualify — Disqualify

# Stages & Brackets
GET    /api/v1/competitions/{id}/stages                        — List stages
POST   /api/v1/competitions/{id}/stages/{stageId}/generate     — Generate bracket
POST   /api/v1/competitions/{id}/stages/{stageId}/complete     — Complete stage (triggers advancement)
GET    /api/v1/competitions/{id}/stages/{stageId}/matches      — List matches

# Matches
POST   /api/v1/competitions/{id}/matches/{matchId}/result      — Report result: { "scores": { "1": 3, "2": 1 } }
POST   /api/v1/competitions/{id}/matches/{matchId}/cancel      — Cancel match

# Standings & Sharing
GET    /api/v1/competitions/{id}/standings                     — Get standings
POST   /api/v1/competitions/{id}/share-token                   — Regenerate overlay share token

# Webhook Configuration
GET    /api/v1/webhook                                         — Get current webhook config
PUT    /api/v1/webhook                                         — Set webhook URL/secret: { "url": "...", "secret": "..." }
```

**Create competition request body:**
```json
{
    "name": "string (required)",
    "type": "tournament|league|race (required)",
    "stage_type": "single_elimination|double_elimination|swiss|round_robin|group_stage (required)",
    "description": "string (optional)",
    "settings": {},
    "external_reference_id": "string (optional — set to LanCore's competition ID)",
    "source_system": "lancore",
    "metadata": {}
}
```

**Bulk add participants request body:**
```json
{
    "participants": [
        { "participant_type": "team|user", "participant_id": 1, "seed": 1 },
        { "participant_type": "team|user", "participant_id": 2, "seed": 2 }
    ]
}
```

**Report result request body:**
```json
{
    "scores": { "1": 3, "2": 1 }
}
```
Scores are keyed by slot number (1 or 2), values are integer scores >= 0.

**Competition response shape** (from LanBrackets):
```json
{
    "data": {
        "id": 1,
        "name": "...",
        "slug": "...",
        "description": null,
        "type": "tournament",
        "status": "draft|planned|registration_open|registration_closed|running|paused|finished|archived",
        "visibility": "private|unlisted|public",
        "starts_at": null,
        "ends_at": null,
        "settings": {},
        "metadata": {},
        "external_reference_id": "lancore-comp-42",
        "source_system": "lancore",
        "share_token": "random32chars...",
        "participants_count": 8,
        "stages_count": 1,
        "stages": [],
        "participants": [],
        "created_at": "...",
        "updated_at": "..."
    }
}
```

**Match response shape:**
```json
{
    "data": {
        "id": 7,
        "round_number": 1,
        "sequence": 1,
        "status": "pending|scheduled|in_progress|finished|cancelled",
        "scheduled_at": null,
        "started_at": null,
        "finished_at": null,
        "winner_participant_id": null,
        "loser_participant_id": null,
        "settings": { "bracket_side": "winners|losers|grand_final|null" },
        "participants": [
            { "competition_participant_id": 1, "slot": 1, "score": null, "result": null },
            { "competition_participant_id": 2, "slot": 2, "score": null, "result": null }
        ],
        "created_at": "...",
        "updated_at": "..."
    }
}
```

**Participant response shape:**
```json
{
    "data": {
        "id": 1,
        "participant_type": "team|user",
        "participant_id": 5,
        "participant_name": "Team Alpha",
        "seed": 1,
        "status": "registered|confirmed|checked_in|disqualified|withdrawn",
        "checked_in_at": null,
        "metadata": {},
        "created_at": "...",
        "updated_at": "..."
    }
}
```

### 2. Competition Model (LanCore side)

LanCore needs its own Competition model to track local state. This model links to LanBrackets via `lanbrackets_competition_id` and `lanbrackets_share_token`.

**Migration — `competitions` table:**
```
id                          — primary key
event_id                    — FK to events table (the LAN event this competition belongs to)
name                        — string
description                 — nullable text
type                        — enum: tournament, league, race
stage_type                  — enum: single_elimination, double_elimination, swiss, round_robin, group_stage
status                      — enum: draft, registration_open, registration_closed, brackets_generating, running, finished, cancelled
registration_opens_at       — nullable datetime
registration_closes_at      — nullable datetime
max_participants            — nullable integer
team_size                   — nullable integer (null = individual/user-based)
lanbrackets_competition_id  — nullable integer (set after syncing to LanBrackets)
lanbrackets_share_token     — nullable string (for OBS overlay URLs)
settings                    — json
metadata                    — json
created_at, updated_at
```

Also create a **`competition_registrations` table** for tracking who signed up:
```
id
competition_id              — FK to competitions
user_id                     — FK to users
team_id                     — nullable FK to teams (if team-based)
seed                        — nullable integer
status                      — enum: registered, confirmed, withdrawn, disqualified
lanbrackets_participant_id  — nullable integer (set after syncing to LanBrackets, maps to LanBrackets' competition_participant.id)
registered_at               — datetime
created_at, updated_at
```

### 3. Integration Hookup

When the "LanBrackets" integration is added in LanCore:
- Store the LanBrackets API base URL and bearer token in the integration config
- Auto-create a webhook endpoint in LanCore for receiving LanBrackets callbacks
- Call `PUT /api/v1/webhook` on LanBrackets to register the webhook URL and a generated HMAC secret

### 4. Webhook Handler

Create a webhook handler for incoming LanBrackets webhooks. LanBrackets sends webhooks with:
- Header `X-LanBrackets-Event` — the event name
- Header `X-LanBrackets-Signature` — HMAC-SHA256 of the raw body using the shared secret
- JSON body:
```json
{
    "event": "bracket.generated|match.result_reported|stage.completed|competition.completed",
    "timestamp": "2026-04-03T12:00:00+00:00",
    "data": { ... }
}
```

**Webhook event payloads:**

`bracket.generated`:
```json
{ "competition_id": 1, "external_reference_id": "lancore-comp-42", "stage_id": 1, "stage_name": "Main Bracket", "match_count": 7 }
```

`match.result_reported`:
```json
{ "competition_id": 1, "external_reference_id": "lancore-comp-42", "stage_id": 1, "match_id": 7, "round_number": 1, "sequence": 1, "winner_participant_id": 3, "loser_participant_id": 5, "scores": { "1": 3, "2": 1 } }
```

`stage.completed`:
```json
{ "competition_id": 1, "external_reference_id": "lancore-comp-42", "stage_id": 1, "stage_name": "Main Bracket" }
```

`competition.completed`:
```json
{ "competition_id": 1, "external_reference_id": "lancore-comp-42" }
```

The handler should:
1. Verify the HMAC signature
2. Look up the LanCore competition by `external_reference_id`
3. Update local state accordingly (e.g., mark competition as `running` after `bracket.generated`, mark as `finished` after `competition.completed`)
4. Optionally broadcast events to the frontend for live updates

### 5. Competition Lifecycle Actions

Create action classes for the competition lifecycle:

**`CreateCompetitionAction`** — Creates the local competition record (draft status). Does NOT call LanBrackets yet.

**`OpenRegistrationAction`** — Sets status to `registration_open`. Validates registration dates.

**`CloseRegistrationAction`** — Sets status to `registration_closed`. This is the trigger point.

**`SyncToLanBracketsAction`** — The key action. Called after registration closes:
1. Create competition in LanBrackets via API (`POST /api/v1/competitions`) with `external_reference_id` set to LanCore's competition ID and `source_system` set to `"lancore"`
2. Store returned `lanbrackets_competition_id` and `share_token` on the local model
3. Bulk-add all confirmed registrations as participants (`POST .../participants/bulk`), mapping LanCore user/team IDs to `participant_id` with seeds based on registration order or admin seeding
4. Generate the bracket (`POST .../stages/{stageId}/generate`)
5. Update local status to `running`

**`ReportMatchResultAction`** — Accepts a match ID and scores, calls `POST .../matches/{matchId}/result` on LanBrackets.

**`CancelCompetitionAction`** — Calls `DELETE /api/v1/competitions/{id}` on LanBrackets (if synced) and updates local status.

### 6. Participant ID Mapping

LanBrackets uses its own participant IDs. LanCore needs a mapping between:
- LanCore `user_id` / `team_id` (local)
- LanBrackets `competition_participant_id` (remote)

Store this mapping on `competition_registrations` as `lanbrackets_participant_id` (nullable integer, set after bulk sync). This is critical for:
- Translating webhook data (`winner_participant_id`) back to LanCore users/teams
- Sending match results with correct slot references

### 7. Configuration

Add to LanCore's config (e.g., `config/services.php` or a dedicated `config/lanbrackets.php`):
```php
'lanbrackets' => [
    'base_url' => env('LANBRACKETS_URL', 'http://lanbrackets.localhost'),
    'token' => env('LANBRACKETS_TOKEN'),
    'webhook_secret' => env('LANBRACKETS_WEBHOOK_SECRET'),
    'auth_secret' => env('LANBRACKETS_AUTH_SECRET'), // shared HMAC secret for signed URL authentication
],
```

## What NOT to build

- Do NOT build bracket visualization — LanBrackets handles this via its overlay page (`/overlay/competitions/{id}?token={share_token}`)
- Do NOT build bracket generation logic — LanBrackets handles all format algorithms
- Do NOT build match scheduling/progression logic — LanBrackets handles auto-advancement
- Do NOT duplicate LanBrackets' match tree in LanCore's database — query LanBrackets' API when needed

## Key integration patterns

1. **LanCore is the source of truth for users, teams, and registrations**
2. **LanBrackets is the source of truth for brackets, matches, and standings**
3. **`external_reference_id`** is the link — always set it to LanCore's competition ID when creating in LanBrackets
4. **Webhook `external_reference_id`** is how LanCore finds which local competition a webhook relates to
5. **The overlay URL** for OBS is: `{LANBRACKETS_URL}/overlay/competitions/{lanbrackets_competition_id}?token={lanbrackets_share_token}` — LanCore just needs to display this URL to admins

### 8. Signed URL Authentication for LanBrackets Web UI

LanBrackets' web UI is protected — only LanCore users with roles `moderator`, `admin`, or `superadmin` can access it. LanCore generates signed redirect URLs that LanBrackets validates.

**Shared secret:** `LANCORE_AUTH_SECRET` — the same value must be configured in both `.env` files. Generate once during integration setup.

**How to generate a signed URL:**

```php
// In LanCore — e.g., a helper method or action class
function generateLanBracketsAuthUrl(User $user, string $redirect = '/'): string
{
    $payload = base64_encode(json_encode([
        'user_id' => $user->id,
        'name' => $user->name,
        'role' => $user->role, // must be 'moderator', 'admin', or 'superadmin'
        'exp' => time() + 300, // 5-minute expiry — link is single-use, session persists after
    ]));

    $signature = hash_hmac('sha256', $payload, config('services.lanbrackets.auth_secret'));

    $baseUrl = config('services.lanbrackets.base_url');

    return "{$baseUrl}/auth/callback?payload={$payload}&signature={$signature}&redirect=" . urlencode($redirect);
}
```

**LanBrackets callback endpoint:** `GET /auth/callback?payload={base64}&signature={hmac}&redirect={path}`

- Validates HMAC-SHA256 signature using the shared secret
- Checks `exp` is not in the past
- Checks `role` is one of: `moderator`, `admin`, `superadmin`
- Creates a session with `{ user_id, name, role, external: true }`
- Redirects to the `redirect` param (defaults to `/`)

**Important:** The `external: true` flag marks these as guest sessions. LanBrackets will never allow these users to access account management features (password change, profile edit, etc.) even if such features are added in the future.

**Where to use signed URLs in LanCore:**
- "Open Brackets" button in competition admin views
- Direct links to specific competitions: set `redirect` to `/competitions/{lanbrackets_competition_id}`
- OBS overlay URL (different mechanism): `{LANBRACKETS_URL}/overlay/competitions/{id}?token={share_token}` — no signed URL needed, uses share_token

## Implementation order

1. Config + LanBracketsClient service (can be tested independently with mocked HTTP)
2. Competition model + migration + registrations
3. Lifecycle actions (CreateCompetition, OpenRegistration, CloseRegistration)
4. SyncToLanBrackets action (the integration centerpiece)
5. Webhook handler for incoming LanBrackets events
6. Integration hookup (auto-setup when "LanBrackets" integration is added)
7. ReportMatchResult action
8. Signed URL generation for LanBrackets web UI access
9. Tests for each step
