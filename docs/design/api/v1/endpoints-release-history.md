# REST API Endpoint for Platform – (Public) Release History

The Release History is used to display published release notes in relation to a project. Its public available.

## Base URL

```text
https://api.rylees.ai/v1
```

## Endpoint

| Method | Endpoint                                  | Description                                     | Auth |
| :----- | :---------------------------------------- | :---------------------------------------------- | :--: |
| GET    | /projects/{project-token}/release-history | Retrieve published release notes for a project. |  n   |

## GET /projects/{uuid}/release-history

Retrieves the published release notes for a project.

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/projects/{uid}/release-notes"
```

### Response

```json
{
  "project": {
    "id": "8d4c8a16-ba88-4377-8d34-6e18c313903b",
    "name": "Member Portal",
    "key": "member-portal"
  },
  "items": [
    {
      "id": "84d1e484-d515-43d5-8e40-f80ca92cc716",
      "version": "1.3.0",
      "body": "This release includes usability improvements and bug fixes.",
      "publishedAt": "2026-06-05T12:00:00Z"
    }
  ]
}
```

## Status Values

| Status      | Description                                                     |
| ----------- | --------------------------------------------------------------- |
| `published` | The release note was successfully published.                    |
| `rejected`  | The release note was rejected by the API and was not published. |
