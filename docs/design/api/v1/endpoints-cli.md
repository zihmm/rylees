# REST API Endpoints for CLI tool

## Base URL

```text
https://api.rylees.ai/v1
```

## Endpoints

| Method | Endpoint                                                                                  | Description                                                                    | Auth |
| ------ | ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ | :--: |
| GET    | [/projects/{project-token}](#get-projects-project-token)                                  | Retrieve project information, including project context and LLM configuration. |  y   |
| POST   | [/projects/{project-token}/release-history](#post-projects-project-token-release-history) | Publish a generated release note to the project's release history.             |  y   |

## Authentication

All API endpoints require authentication using a personal API token.

Include the token in the `Authorization` header of every request:

```http
Authorization: Bearer <api-token>
```

# GET /projects/{project-token}

Retrieves project information required by the CLI tool to generate accurate release notes. The response includes customer context and LLM configuration settings.

## Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/projects/{project-token}" \
  -H "Authorization: Bearer <api-token>"
```

## Response

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
    "temperature": 0.4
  }
}
```

# POST /projects/{project-token}/release-history

Publishes a generated release note and adds it to the project's release history.

## Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/projects/{project-token}" \
  -H "Authorization: Bearer <api-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "startRef": "8f2a1c4",
    "endRef": "1e9p37x",
    "type": "commits",
    "branchName": "development",
    "body": "Several usability improvements and bug fixes have been implemented.",
    "version": {
      "major": 1,
      "minor": 3,
      "patch": 0
    }
  }'
```

## Request Fields

| Field      | Description                                                   |
| ---------- | ------------------------------------------------------------- |
| startRef   | Git reference representing the start of the comparison range. |
| endRef     | Git reference representing the end of the comparison range.   |
| type       | Source type used for generation (`commits`, `tag`).           |
| branchName | Name of the source branch.                                    |
| body       | Generated release note content.                               |
| version    | Semantic version associated with the release note.            |

## Response

```json
{
  "id": "8d4c8a16-ba88-4377-8d34-6e18c313903b",
  "status": "published",
  "version": "1.3.0"
}
```

## Status Values

| Status    | Description                                                     |
| --------- | --------------------------------------------------------------- |
| published | The release note was successfully published.                    |
| rejected  | The release note was rejected by the API and was not published. |
