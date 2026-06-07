# REST API Endpoints – Public Release History

The Release History API serves published release notes publicly without authentication. Customer subdomains (`{slug}.rylees.ai`) route to the Release History SPA, which reads the slug from `window.location.hostname` and the project key from `window.location.pathname`.

## Base URL

```text
https://api.rylees.ai/v1/public
```

## Endpoints

| Method | Endpoint | Description | Auth |
| :----- | :------- | :---------- | :--: |
| GET | [/public/release-history/{customerSlug}/{projectKey}](#get-publicrelease-historycustomerslugprojectkey) | Retrieve published release notes for a project. | n |
| GET | [/public/release-history/{customerSlug}/{projectKey}/translate](#get-publicrelease-historycustomerslugprojectkeytranslate) | Return all release notes translated into a target language. | n |

---

## GET /public/release-history/{customerSlug}/{projectKey}

Retrieves published release notes for a project, ordered newest first.

**Parameter resolution:**
- `{customerSlug}` resolves via `organisations.slug` joined through `customers.organisation_id`
- `{projectKey}` resolves via `projects.key` scoped to that customer

### Request

```bash
curl -X GET "https://api.rylees.ai/v1/public/release-history/acme-ltd/member-portal"
```

### Response `200 OK`

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

Returns `404 not_found` if the slug/key combination does not resolve.

---

## GET /public/release-history/{customerSlug}/{projectKey}/translate

Translates all release note bodies for the project into the target language using the AI module (GPT-5.4, temperature 0.3). The source language is always treated as German (`de`).

### Query parameters

| Parameter | Required | Values | Description |
| --------- | -------- | ------ | ----------- |
| `language` | yes | `de`, `en`, `fr` | Target language |

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/public/release-history/acme-ltd/member-portal/translate?language=fr"
```

### Response `200 OK`

```json
{
  "language": "fr",
  "items": [
    {
      "id": "84d1e484-d515-43d5-8e40-f80ca92cc716",
      "version": "1.3.0",
      "body": "Cette version inclut des améliorations d'ergonomie et des corrections de bogues."
    }
  ]
}
```

Returns `422 validation_error` if `language` is missing or not one of `de`, `en`, `fr`.

> The translation endpoint may take up to 30 seconds. The frontend must display a loading skeleton per item while the request is in flight.
