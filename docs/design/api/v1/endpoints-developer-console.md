# REST API Endpoints – Developer Console

The Developer Console is used by authenticated developers to manage customers, projects, and API / project tokens.

## Base URL

```text
https://api.rylees.ai/v1
```

## Endpoints

| Method | Endpoint | Description | Auth |
| :----- | :------- | :---------- | :--: |
| POST | [/auth/login](#post-authlogin) | Log in and receive an access token. | n |
| POST | [/auth/logout](#post-authlogout) | Log out and revoke the current access token. | y |
| POST | [/users/register](#post-usersregister) | Register a new user with profile and organisation. | n |
| GET | [/users/activate](#get-usersactivate) | Activate a user account via email token. | n |
| GET | [/users/me](#get-usersme) | Retrieve the authenticated user, profile, and organisation. | y |
| PATCH | [/users/me](#patch-usersme) | Update user profile, organisation, or password. | y |
| DELETE | [/users/me](#delete-usersme) | Soft-delete own user account. | y |
| GET | [/customers](#get-customers) | Retrieve paginated list of customers. | y |
| POST | [/customers](#post-customers) | Create a new customer. | y |
| GET | [/customers/{id}](#get-customersid) | Retrieve full details of a specific customer. | y |
| PATCH | [/customers/{id}](#patch-customersid) | Update customer organisation fields, industry, or description. | y |
| POST | [/customers/{id}/contacts](#post-customersidcontacts) | Add a contact to a customer. | y |
| PATCH | [/customers/{id}/contacts/{contactId}](#patch-customersidcontactscontactid) | Update a contact. | y |
| DELETE | [/customers/{id}/contacts/{contactId}](#delete-customersidcontactscontactid) | Delete a contact (soft). | y |
| GET | [/customers/{id}/projects](#get-customersidprojects) | List all projects for a customer. | y |
| POST | [/customers/{id}/projects](#post-customersidprojects) | Create a new project for a customer. | y |
| GET | [/customers/{id}/projects/{projectId}](#get-customersidprojectsprojectid) | Retrieve full project details. | y |
| PATCH | [/customers/{id}/projects/{projectId}](#patch-customersidprojectsprojectid) | Update project fields. | y |

## Authentication

All protected endpoints require a Sanctum session Bearer token issued by `POST /auth/login`.

```http
Authorization: Bearer <access-token>
```

Token lifetime: 60 minutes (`expires_in: 3600`).

### Authentication errors

| Status | Code | Description |
| ------ | ---- | ----------- |
| `401` | `unauthenticated` | Missing, expired, or invalid token. |
| `403` | `inactive_user` | Account exists but is not activated. |
| `403` | `forbidden` | Resource belongs to another user. |
| `404` | `not_found` | Resource does not exist. |
| `422` | `validation_error` | Request payload failed validation. |

All error responses use the shape:

```json
{ "message": "Human-readable description.", "code": "snake_case_code" }
```

---

## POST /auth/login

Authenticates an active user and returns an access token.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "jane.doe@example.com",
    "password": "CorrectHorseBatteryStaple!42"
  }'
```

### Response `200 OK`

```json
{
  "token_type": "Bearer",
  "access_token": "eyJhbGciOi...",
  "expires_in": 3600,
  "user": {
    "id": "1dd7952f-5a42-4401-abbc-03d30fb63ef6",
    "username": "jane.doe@example.com",
    "is_active": true,
    "profile": {
      "id": "f39eec8a-1811-4f2c-86f1-a7d09fe1fc6e",
      "firstname": "Jane",
      "lastname": "Doe"
    },
    "organisation": {
      "id": "7b20f9a1-e991-483c-83ce-dcad02450697",
      "name": "Doe Digital GmbH"
    }
  }
}
```

Returns `403 inactive_user` if `users.is_active = false`.
Returns `401 unauthenticated` on wrong credentials (same response whether username exists or not).

---

## POST /auth/logout

Revokes the current Sanctum token.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/auth/logout" \
  -H "Authorization: Bearer <access-token>"
```

### Response `204 No Content`

---

## POST /users/register

Creates an `organisations` row, a `users` row, and a `user_profiles` row. Sets `is_active = false`, generates `api_key` (64-char random), generates `activation_token` (64-char random), and sends an activation email.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/users/register" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "jane.doe@example.com",
    "password": "CorrectHorseBatteryStaple!42",
    "profile": {
      "firstname": "Jane",
      "lastname": "Doe"
    },
    "organisation": {
      "name": "Doe Digital GmbH",
      "street": "Kernackerweg 7",
      "postcode": "5000",
      "city": "Aarau",
      "website": "https://doedigital.example",
      "email": "hello@doedigital.example"
    }
  }'
```

**Validation rules:**
- `username`: required, valid email format, unique in `users`
- `password`: required, min 12 characters
- `profile.firstname`, `profile.lastname`: required
- `organisation.name`: required

### Response `201 Created`

```json
{
  "user": {
    "id": "1dd7952f-5a42-4401-abbc-03d30fb63ef6",
    "username": "jane.doe@example.com",
    "is_active": false,
    "activated_at": null,
    "created_at": "2026-06-05T10:00:00Z"
  },
  "profile": {
    "id": "f39eec8a-1811-4f2c-86f1-a7d09fe1fc6e",
    "firstname": "Jane",
    "lastname": "Doe"
  },
  "organisation": {
    "id": "7b20f9a1-e991-483c-83ce-dcad02450697",
    "name": "Doe Digital GmbH",
    "slug": "doe-digital-gmbh"
  }
}
```

---

## GET /users/activate

Activates a user account via the token sent in the registration email.

```
Query parameter: token=<activation_token>
```

Sets `users.is_active = true`, `activated_at = now()`, clears `activation_token = null`.

### Request

```bash
curl -X GET "https://api.rylees.ai/v1/users/activate?token=<activation_token>"
```

### Response `200 OK`

```json
{ "message": "Account activated successfully." }
```

Returns `404 not_found` if the token is not found or has already been cleared.

---

## GET /users/me

Returns the authenticated user with profile and organisation.

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/users/me" \
  -H "Authorization: Bearer <access-token>"
```

### Response `200 OK`

```json
{
  "id": "1dd7952f-5a42-4401-abbc-03d30fb63ef6",
  "username": "jane.doe@example.com",
  "is_active": true,
  "activated_at": "2026-06-05T10:05:00Z",
  "api_key": "<64-char-api-key>",
  "profile": {
    "id": "f39eec8a-1811-4f2c-86f1-a7d09fe1fc6e",
    "firstname": "Jane",
    "lastname": "Doe"
  },
  "organisation": {
    "id": "7b20f9a1-e991-483c-83ce-dcad02450697",
    "slug": "doe-digital-gmbh",
    "name": "Doe Digital GmbH",
    "street": "Kernackerweg 7",
    "postcode": "5000",
    "city": "Aarau",
    "website": "https://doedigital.example",
    "email": "hello@doedigital.example"
  }
}
```

> `api_key` is exposed **only** in this endpoint, never in list responses.

---

## PATCH /users/me

Updates profile and/or organisation fields. Password change requires `current_password`.

### Request

All fields are optional.

```bash
curl -X PATCH \
  "https://api.rylees.ai/v1/users/me" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "profile": { "firstname": "Jane", "lastname": "Doe" },
    "organisation": {
      "name": "Doe Digital GmbH",
      "street": "...",
      "postcode": "...",
      "city": "...",
      "website": "...",
      "email": "..."
    },
    "current_password": "OldPassword!42",
    "new_password": "NewPassword!42"
  }'
```

If `new_password` is provided, `current_password` must also be provided and must match. On mismatch: `422 validation_error`.

### Response `200 OK`

Same shape as `GET /users/me`.

---

## DELETE /users/me

Soft-deletes the `users` and `user_profiles` rows. Revokes all active Sanctum tokens. Does **not** delete the `organisations` row.

### Response `204 No Content`

---

## GET /customers

Returns a paginated list of non-deleted customers owned by the authenticated developer.

### Query parameters

| Parameter | Type | Default | Description |
| --------- | ---- | ------- | ----------- |
| `page` | integer | `1` | Page number |
| `per_page` | integer | `20` | Items per page (max 100) |

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/customers?page=1&per_page=20" \
  -H "Authorization: Bearer <access-token>"
```

### Response `200 OK`

```json
{
  "data": [
    {
      "id": "efaf3def-b091-46e1-b3d9-6c3f7f2e2597",
      "description": "Customer's optional description.",
      "projects_count": 3,
      "organisation": {
        "id": "7b20f9a1-e991-483c-83ce-dcad02450697",
        "slug": "acme-ltd",
        "name": "Acme Ltd.",
        "street": "Kernackerweg 7",
        "postcode": "5000",
        "city": "Aarau",
        "website": "https://acme.com",
        "email": "info@acme.com"
      },
      "main_contact": {
        "id": "7bef92ad-e299-461c-82fc-f4109282af1f",
        "firstname": "John",
        "lastname": "Doe",
        "email": "john.doe@acme.com"
      },
      "industry": {
        "id": "ab4588e7-9f52-470e-98e7-aa55c8c4b78d",
        "name": "Architecture"
      },
      "created_at": "2026-06-05T10:00:00Z",
      "updated_at": "2026-06-05T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "total": 42
  }
}
```

> `projects_count` is an integer count of non-deleted projects for that customer. Used by the frontend dashboard and customer list to display project counts without N+1 queries.

---

## POST /customers

Creates a new customer. Also creates the customer's `organisations` row. Optionally creates an initial `customer_contacts` row and sets `main_contact_id`.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/customers" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "organisation": {
      "name": "Acme Ltd.",
      "street": "Kernackerweg 7",
      "postcode": "5000",
      "city": "Aarau",
      "website": "https://acme.com",
      "email": "info@acme.com"
    },
    "industry_id": "ab4588e7-9f52-470e-98e7-aa55c8c4b78d",
    "description": "Customer organisation for architecture-related software projects.",
    "main_contact": {
      "firstname": "John",
      "lastname": "Doe",
      "email": "john.doe@acme.com"
    }
  }'
```

**Validation rules:**
- `organisation.name`: required
- `industry_id`: optional; if provided must reference a valid `industry_types.id`
- `main_contact.firstname`, `main_contact.lastname`, `main_contact.email`: all required if `main_contact` key is present

**Creation order:** create `organisations` row → create `customers` row → if `main_contact` present, create `customer_contacts` row → set `customers.main_contact_id`.

### Response `201 Created`

```json
{
  "id": "efaf3def-b091-46e1-b3d9-6c3f7f2e2597",
  "organisation": {
    "id": "7b20f9a1-e991-483c-83ce-dcad02450697",
    "name": "Acme Ltd.",
    "slug": "acme-ltd"
  },
  "created_at": "2026-06-05T10:00:00Z"
}
```

---

## GET /customers/{id}

Returns full customer details including contacts and projects.

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/customers/{id}" \
  -H "Authorization: Bearer <access-token>"
```

### Response `200 OK`

```json
{
  "id": "efaf3def-b091-46e1-b3d9-6c3f7f2e2597",
  "description": "Customer organisation for architecture-related software projects.",
  "organisation": {
    "id": "7b20f9a1-e991-483c-83ce-dcad02450697",
    "slug": "acme-ltd",
    "name": "Acme Ltd.",
    "street": "Kernackerweg 7",
    "postcode": "5000",
    "city": "Aarau",
    "website": "https://acme.com",
    "email": "info@acme.com"
  },
  "industry": {
    "id": "ab4588e7-9f52-470e-98e7-aa55c8c4b78d",
    "name": "Architecture"
  },
  "contacts": [
    {
      "id": "7bef92ad-e299-461c-82fc-f4109282af1f",
      "firstname": "John",
      "lastname": "Doe",
      "email": "john.doe@acme.com"
    }
  ],
  "main_contact": {
    "id": "7bef92ad-e299-461c-82fc-f4109282af1f",
    "firstname": "John",
    "lastname": "Doe",
    "email": "john.doe@acme.com"
  },
  "projects": [
    {
      "id": "8d4c8a16-ba88-4377-8d34-6e18c313903b",
      "name": "Member Portal",
      "key": "member-portal"
    }
  ]
}
```

Returns `404 not_found` if not found or not owned by the caller.

---

## PATCH /customers/{id}

Updates organisation fields, industry, and description. **Contacts are managed via their own sub-resource endpoints** — they cannot be updated through this endpoint.

### Request

All fields are optional.

```bash
curl -X PATCH \
  "https://api.rylees.ai/v1/customers/{id}" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "organisation": {
      "name": "Acme Ltd.",
      "street": "...",
      "postcode": "...",
      "city": "...",
      "website": "...",
      "email": "..."
    },
    "industry_id": "ab4588e7-9f52-470e-98e7-aa55c8c4b78d",
    "description": "Updated description."
  }'
```

### Response `200 OK`

Same shape as `GET /customers/{id}`.

---

## POST /customers/{id}/contacts

Adds a new contact to the customer.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/customers/{id}/contacts" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "firstname": "Jane",
    "lastname": "Smith",
    "email": "jane.smith@acme.com"
  }'
```

**Validation rules:** `firstname`, `lastname`, `email` are all required; `email` must be valid email format.

### Response `201 Created`

```json
{
  "id": "a1b2c3d4-...",
  "firstname": "Jane",
  "lastname": "Smith",
  "email": "jane.smith@acme.com"
}
```

---

## PATCH /customers/{id}/contacts/{contactId}

Updates an existing contact. All fields are optional.

### Request

```bash
curl -X PATCH \
  "https://api.rylees.ai/v1/customers/{id}/contacts/{contactId}" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "firstname": "Janet",
    "email": "janet.smith@acme.com"
  }'
```

### Response `200 OK`

```json
{
  "id": "a1b2c3d4-...",
  "firstname": "Janet",
  "lastname": "Smith",
  "email": "janet.smith@acme.com"
}
```

---

## DELETE /customers/{id}/contacts/{contactId}

Soft-deletes a contact. If this contact is currently set as `customers.main_contact_id`, that field is set to `NULL` on the customer.

### Response `204 No Content`

---

## GET /customers/{id}/projects

Returns all non-deleted projects for the given customer.

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/customers/{id}/projects" \
  -H "Authorization: Bearer <access-token>"
```

### Response `200 OK`

```json
{
  "data": [
    {
      "id": "8d4c8a16-ba88-4377-8d34-6e18c313903b",
      "name": "Member Portal",
      "key": "member-portal",
      "description": "A customer-facing portal for membership management.",
      "token": "<64-char-token>",
      "llm": {
        "temperature": 0.5,
        "tonality": "professional"
      },
      "created_at": "2026-06-05T10:15:00Z"
    }
  ]
}
```

---

## POST /customers/{id}/projects

Creates a new project under the given customer. Also creates one `release_histories` row for the project.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/customers/{id}/projects" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Member Portal",
    "description": "A customer-facing portal for membership management.",
    "llm_tonality_id": "4be7b39b-4b07-402e-a99f-b254d5bd8565",
    "llm_temperature_id": "108f28ec-0c48-465a-8497-67edd8dd9bc4"
  }'
```

**Validation rules:**
- `name`: required
- `llm_tonality_id`: required, must reference a valid `llm_tonality_types.id`
- `llm_temperature_id`: required, must reference a valid `llm_temperature_types.id`

**Auto-generated fields:** `projects.key` (slug from `name`, unique per customer); `projects.token` (`Str::random(64)`).

### Response `201 Created`

```json
{
  "id": "8d4c8a16-ba88-4377-8d34-6e18c313903b",
  "name": "Member Portal",
  "key": "member-portal",
  "token": "<64-char-token>",
  "created_at": "2026-06-05T10:15:00Z"
}
```

---

## GET /customers/{id}/projects/{projectId}

Returns full project details including LLM configuration and the customer's organisation slug (used by the frontend to load release notes via the public endpoint).

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/customers/{id}/projects/{projectId}" \
  -H "Authorization: Bearer <access-token>"
```

### Response `200 OK`

```json
{
  "id": "8d4c8a16-ba88-4377-8d34-6e18c313903b",
  "name": "Member Portal",
  "key": "member-portal",
  "description": "A customer-facing portal for membership management.",
  "token": "<64-char-token>",
  "customer": {
    "id": "efaf3def-b091-46e1-b3d9-6c3f7f2e2597",
    "name": "Acme Ltd.",
    "industry": "Architecture",
    "organisation_slug": "acme-ltd"
  },
  "llm": {
    "temperature": 0.5,
    "tonality": "professional"
  },
  "created_at": "2026-06-05T10:15:00Z",
  "updated_at": "2026-06-05T10:15:00Z"
}
```

> `token` is exposed in single-project responses and the create response only — never in global list responses.
> `customer.organisation_slug` allows the frontend to construct the public release history URL: `GET /public/release-history/{organisation_slug}/{key}`.

Returns `404 not_found` if not found or not accessible to the caller.

---

## PATCH /customers/{id}/projects/{projectId}

Updates project fields. Only `name`, `description`, `llm_tonality_id`, and `llm_temperature_id` may be changed. `token` and `key` are immutable.

### Request

All fields are optional.

```bash
curl -X PATCH \
  "https://api.rylees.ai/v1/customers/{id}/projects/{projectId}" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Member Portal v2",
    "description": "Updated description.",
    "llm_tonality_id": "...",
    "llm_temperature_id": "..."
  }'
```

### Response `200 OK`

Same shape as `GET /customers/{id}/projects/{projectId}`.
