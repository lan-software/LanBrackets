# LanBrackets REST API

## Authentication

All API endpoints require a valid bearer token in the `Authorization` header.

```
Authorization: Bearer lbt_<random-chars>
```

### Token Management

Tokens are managed via artisan commands:

```bash
# Create a new token (auto-generate)
vendor/bin/sail artisan api-token:create "LanCore Production" --generate

# Create with a specific token value
vendor/bin/sail artisan api-token:create "LanCore Production" --token=lbt_your_custom_token_here

# List all tokens
vendor/bin/sail artisan api-token:list

# Revoke a token
vendor/bin/sail artisan api-token:revoke {id}
```

Tokens are SHA-256 hashed before storage. The plain text is shown only at creation.

## Base URL

All endpoints are under `/api/v1/`.

---

## Endpoints

### List Competitions

```
GET /api/v1/competitions
```

Returns paginated list of competitions with participant and stage counts.

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Summer Tournament",
      "slug": "summer-tournament",
      "type": "tournament",
      "status": "draft",
      "participants_count": 8,
      "stages_count": 1,
      "created_at": "2026-04-03T10:00:00Z"
    }
  ],
  "links": { "..." },
  "meta": { "..." }
}
```

### Create Competition

```
POST /api/v1/competitions
```

**Request Body:**
```json
{
  "name": "Summer Tournament",
  "type": "tournament",
  "stage_type": "single_elimination",
  "description": "Optional description",
  "settings": {
    "third_place_match": true
  }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Competition name |
| `type` | string | Yes | `tournament`, `league`, or `race` |
| `stage_type` | string | Yes | `single_elimination`, `double_elimination`, `round_robin`, `swiss`, `group_stage` |
| `description` | string | No | Description |
| `settings` | object | No | Format-specific settings (see formats.md) |

**Response (200):** `CompetitionResource`

### Show Competition

```
GET /api/v1/competitions/{id}
```

Returns competition with stages and participants.

**Response (200):** `CompetitionResource` with loaded stages and participants.

### List Stages

```
GET /api/v1/competitions/{id}/stages
```

**Response (200):** Array of `CompetitionStageResource` with match counts.

### Add Participant

```
POST /api/v1/competitions/{id}/participants
```

**Request Body:**
```json
{
  "participant_type": "team",
  "participant_id": 42,
  "seed": 1
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `participant_type` | string | Yes | `team` or `user` |
| `participant_id` | integer | Yes | ID of the Team or User |
| `seed` | integer | No | Seed number (auto-assigned if omitted) |

**Response (201):** `CompetitionParticipantResource`

### Generate Bracket

```
POST /api/v1/competitions/{id}/stages/{stage_id}/generate
```

Generates the bracket/match structure for a stage. The stage must be in `Pending` status and have enough participants.

**Response (200):**
```json
{
  "message": "Bracket generated successfully.",
  "stage": { "..." }
}
```

### List Matches

```
GET /api/v1/competitions/{id}/stages/{stage_id}/matches
```

Returns all matches for a stage, ordered by round and sequence.

**Response (200):** Array of `CompetitionMatchResource` with participant details.

### Report Match Result

```
POST /api/v1/competitions/{id}/matches/{match_id}/result
```

**Request Body:**
```json
{
  "scores": {
    "1": 3,
    "2": 1
  }
}
```

Keys are slot numbers (1 and 2), values are scores.

**Response (200):** `CompetitionMatchResource` with updated status and results.

### Get Standings

```
GET /api/v1/competitions/{id}/standings
```

Returns computed standings (wins, losses, draws) for all participants.

**Response (200):**
```json
{
  "data": [
    {
      "participant_id": 1,
      "participant_name": "Team Alpha",
      "seed": 1,
      "wins": 3,
      "losses": 1,
      "draws": 0
    }
  ]
}
```

---

## Error Responses

### 401 Unauthenticated
```json
{ "message": "Unauthenticated." }
```

### 422 Validation Error
```json
{
  "message": "The name field is required.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

### 404 Not Found
Standard Laravel 404 for invalid IDs.

### 500 Server Error
Returned for unhandled exceptions (e.g. generating a bracket with insufficient participants).

---

## Typical Workflow

1. **Create** a competition: `POST /competitions`
2. **Add participants**: `POST /competitions/{id}/participants` (repeat for each)
3. **Generate** the bracket: `POST /competitions/{id}/stages/{stage_id}/generate`
4. **List matches**: `GET /competitions/{id}/stages/{stage_id}/matches`
5. **Report results**: `POST /competitions/{id}/matches/{match_id}/result` (repeat for each match)
6. **Check standings**: `GET /competitions/{id}/standings`
