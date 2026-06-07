# REST API Endpoints – CLI Tool

The CLI tool uses two endpoints to fetch project context before generation and to publish the approved release note.

## Base URL

```text
https://api.rylees.ai/v1
```

## Endpoints

| Method | Endpoint | Description | Auth |
| ------ | -------- | ----------- | :--: |
| GET | [/projects/{projectToken}](#get-projectsprojecttoken) | Retrieve project context and LLM configuration. | y |
| POST | [/projects/{projectToken}/release-history](#post-projectsprojecttokenrelease-history) | Publish a generated release note. | y |

## Authentication

CLI endpoints authenticate using the developer's personal `api_key` (stored in `users.api_key`, generated on registration). This is **not** a Sanctum session token — it is a permanent 64-character key used as a Bearer token.

```http
Authorization: Bearer <api_key>
```

The API resolves the user by looking up `users.api_key = <token>` where `users.is_active = true`. This is handled by a custom `AuthenticateWithApiKey` middleware that runs before the standard Sanctum guard.

---

## GET /projects/{projectToken}

Retrieves project information required by the CLI to generate release notes. `{projectToken}` resolves via `projects.token`. The API verifies that the project's customer belongs to the authenticated developer.

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/projects/{projectToken}" \
  -H "Authorization: Bearer <api_key>"
```

### Response `200 OK`

```json
{
  "id": "8d4c8a16-ba88-4377-8d34-6e18c313903b",
  "name": "Member Portal",
  "key": "member-portal",
  "description": "A customer-facing portal for membership management.",
  "customer": {
    "id": "efaf3def-b091-46e1-b3d9-6c3f7f2e2597",
    "name": "Acme Ltd.",
    "industry": "Architecture"
  },
  "llm": {
    "temperature": 0.5,
    "tonality": "professional"
  }
}
```

The CLI uses `customer.name`, `customer.industry`, `llm.temperature`, `llm.tonality`, and `description` to build the LLM prompts. If `RYLEES_LLM_TEMPERATURE` is set in the local `.env`, it overrides `llm.temperature`.

---

## POST /projects/{projectToken}/release-history

Publishes a release note. The **version number is computed server-side** — the CLI sends only the bump direction, not the target version.

### Version computation (server-side)

1. Find the latest `release_note` for this project's `release_history`, ordered by `created_at DESC`.
2. If none exists, treat current version as `0.0.0`.
3. Apply the bump:
   - `major` → `(major+1).0.0`
   - `minor` → `major.(minor+1).0`
   - `patch` → `major.minor.(patch+1)`
4. Persist `version_major`, `version_minor`, `version_patch` on the new `release_notes` row.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/projects/{projectToken}/release-history" \
  -H "Authorization: Bearer <api_key>" \
  -H "Content-Type: application/json" \
  -d '{
    "startRef": "8f2a1c4",
    "endRef": "1e9p37x",
    "type": "commits",
    "branchName": "development",
    "body": "Several usability improvements and bug fixes have been implemented.",
    "versionBump": "minor"
  }'
```

### Request fields

| Field | Type | Required | Values | Description |
| ----- | ---- | -------- | ------ | ----------- |
| `startRef` | string | yes | — | Git ref (hash or tag) for range start |
| `endRef` | string | yes | — | Git ref (hash or tag) for range end |
| `type` | string | yes | `commits`, `tag` | Determines which columns to populate |
| `branchName` | string | no | — | Source branch name |
| `body` | string | yes | — | Approved release note text |
| `versionBump` | string | yes | `major`, `minor`, `patch` | Bump direction; version computed server-side |

**Column population logic:**
- `type = "commits"` → populates `commithash_start` and `commithash_end`; leaves `tag_start` and `tag_end` null
- `type = "tag"` → populates `tag_start` and `tag_end`; leaves `commithash_start` and `commithash_end` null

### Response `201 Created`

```json
{
  "id": "84d1e484-d515-43d5-8e40-f80ca92cc716",
  "status": "published",
  "version": "0.1.0"
}
```

The CLI prints `status` and `version` to stdout after a successful publish.
