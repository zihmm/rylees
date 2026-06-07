# REST API Endpoint for Platform – Developer Console

The Developer Console is used by authenticated developers to manage customers, projects and API / Project tokens.

## Base URL

```text
https://api.rylees.ai/v1
```

## Endpoints

| Method | Endpoint                                                     | Description                                                | Auth |
| :----- | :----------------------------------------------------------- | :--------------------------------------------------------- | :--: |
| GET    | [/customers](#get-customers)                                 | Retrieve all customers                                     |  y   |
| POST   | [/customers](#post-customers)                                | Create a new customer.                                     |  y   |
| PATCH  | [/customers](#patch-customers)                               | Update customer.                                           |  y   |
| GET    | [/customers/{uuid}](#get-customers-uuid)                     | Retrieve details of a specific customer.                   |  y   |
| POST   | [/customers/{uuid}/projects](#post-customers-uuid-projects)  | Create a new project for a customer.                       |  y   |
| GET    | [/customers/{uuid}/projects/{uuid}](#get-customers-uuid-projects-uuid)                       | Retrieve project details.                                  |  y   |
| PATCH  | [/customers/{uuid}/projects/{uuid}](#patch-customers-uuid-projects-uuid) | Update customers project informations                      |  y   |
| POST   | [/users/register](#post-usersregister)                      | Register a new user with profile and organisation.         |  n   |
| GET    | [/users/me](#get-usersme)                                   | Retrieve the authenticated user, profile and organisation. |  y   |
| PATCH  | [/users/me](#patch-usersme)                                 | Updates user informations                                  |  y   |
| DELETE | [/users/me](#delete-usersme)                                | Sfoto delete own user account                              |  y   |
| POST   | [/auth/login](#post-authlogin)                              | Log in and receive an access token.                        |  n   |
| POST   | [/auth/logout](#post-authlogout)                            | Log out and revoke the current access token.               |  y   |

## Authentication

All protected API endpoints require authentication using a user session or bearer token.

```http
Authorization: Bearer <access-token>
```

### Token lifetime

| Token type      | Usage                                            | Recommended lifetime |
| --------------- | ------------------------------------------------ | -------------------- |
| `access_token`  | Authorizes API requests                          | 15-60 minutes        |
| `refresh_token` | Optional token used to obtain a new access token | 7-30 days            |

### Authentication errors

| Status | Code               | Description                                                                     |
| ------ | ------------------ | ------------------------------------------------------------------------------- |
| `401`  | `unauthenticated`  | Missing, expired or invalid token.                                              |
| `403`  | `inactive_user`    | The user exists but `users.is_active` is false or the account is not activated. |
| `422`  | `validation_error` | Request payload failed validation.                                              |

## GET /customers

Retrieves all customers available to the authenticated developer company.

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/customers" \
  -H "Authorization: Bearer <access-token>"
```

### Response

```json
{
  "items": [
    {
      "id": "efaf3def-b091-46e1-b3d9-6c3f7f2e2597",
      "description": "Customers optional description..."
      "organisation": {
        "id": "7b20f9a1-e991-483c-83ce-dcad02450697",
        "name": "Acme Ltd.",
        "street": "Kernackerweg 7",
        "postcode": 5000,
        "city": "Aarau",
        "website": "https://acem.com",
        "email": "info@acme.com",
      },
      "main_contact": {
        "id": "7bef92ad-e299-461c-82fc-f4109282af1f",
        "firsname": "John",
        "lastname": "Doe",
        "eamil": "john.doe@example.com"
      },
      "industry": {
        "id": "ab4588e7-9f52-470e-98e7-aa55c8c4b78d",
        "name": "Architecture"
      },
      "created_at": "2026-06-05T10:00:00Z",
      "updated_at": "2026-06-05T10:00:00Z"
    }
  ]
}
```

## POST /customers

Creates a new customer.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/console/customers" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Acme Ltd.",
    "street": "Kernackerweg 7",
    "postcode": 5000,
    "city": "Aarau",
    "website": "https://acem.com",
    "email": "info@acme.com",
    "industry_id": "3a5c55f1-cae0-4d93-b6ac-3835d7708762",
    "user_id": "1dd7952f-5a42-4401-abbc-03d30fb63ef6",
    "main_contact_id": "1dd7952f-5a42-4401-abbc-03d30fb63ef6"
    "description": "Customer organization for architecture-related software projects."
  }'
```

### Response

```json
{
  "id": "efaf3def-b091-46e1-b3d9-6c3f7f2e2597",
  "name": "Acme Ltd.",
  "createdAt": "2026-06-05T10:00:00Z"
}
```

## GET /customers/{uuid}

Retrieves details of a specific customer.

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/console/customers/{uuid}" \
  -H "Authorization: Bearer <access-token>"
```

### Response

```json
{
  "id": "efaf3def-b091-46e1-b3d9-6c3f7f2e2597",
  "name": "Acme Ltd.",
  "description": "Customer organization for architecture-related software projects.",
  "industry": {
    "id": "ab4588e7-9f52-470e-98e7-aa55c8c4b78d",
    "name": "Architecture"
  },
  "organisation": {
    "id": "7b20f9a1-e991-483c-83ce-dcad02450697",
    "name": "Acme Ltd.",
    "street": "Kernackerweg 7",
    "postcode": 5000,
    "city": "Aarau",
    "website": "https://acem.com",
    "email": "info@acme.com"
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

## PATCH /customers

Update customers informations. See [POST /customers](#post-customers) for request.

## POST /customers/{uuid}/projects

Creates a new project for a customer.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/customers/{uiud}/projects" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Member Portal",
    "description": "A customer-facing portal for membership management.",
    "llm_tonality_id": "4be7b39b-4b07-402e-a99f-b254d5bd8565",
    "llm_temperature_id": "108f28ec-0c48-465a-8497-67edd8dd9bc4"
  }'
```

### Response

```json
{
  "id": "8d4c8a16-ba88-4377-8d34-6e18c313903b",
  "name": "Member Portal",
  "key": "member-portal",
  "createdAt": "2026-06-05T10:15:00Z"
}
```

## GET /customers/{uuid}/projects/{uuid}

Retrieves project details, including customer context and release note settings.

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/projects/{uuid}" \
  -H "Authorization: Bearer <access-token>"
```

### Response

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
    "temperature": 0.4,
    "tonality": "professional"
  }
}
```

## PATCH /customers/{uuid}/projects/{uuid}

Updates project 


## POST /users/register

Registers a new developer-console user, creates the user's profile, and creates or links the user's organisation.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/auth/register" \
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
    "street": "Kernackerweg 7",
    "postcode": "5000",
    "city": "Aarau",
    "website": "https://doedigital.example",
    "email": "hello@doedigital.example"
  }
}
```

## GET /users/me

Returns the authenticated user with profile and organisation context.

### Request

```bash
curl -X GET \
  "https://api.rylees.ai/v1/auth/me" \
  -H "Authorization: Bearer <access-token>"
```

### Response `200 OK`

```json
{
  "id": "1dd7952f-5a42-4401-abbc-03d30fb63ef6",
  "username": "jane.doe@example.com",
  "is_active": true,
  "activated_at": "2026-06-05T10:05:00Z",
  "profile": {
    "id": "f39eec8a-1811-4f2c-86f1-a7d09fe1fc6e",
    "firstname": "Jane",
    "lastname": "Doe"
  },
  "organisation": {
    "id": "7b20f9a1-e991-483c-83ce-dcad02450697",
    "name": "Doe Digital GmbH",
    "street": "Kernackerweg 7",
    "postcode": "5000",
    "city": "Aarau",
    "website": "https://doedigital.example",
    "email": "hello@doedigital.example"
  }
}
```

## PATCH /users/me

...

## DELETE /users/me

Soft-deletes a user account by setting `users.deleted_at`, revokes active tokens, and prevents future login. The linked `user_profile` should also be soft-deleted. The organisation is not deleted automatically, because it may be referenced by other profiles or customers.

### Request

```bash
curl -X DELETE \
  "https://api.rylees.ai/v1/users/{uuid}" \
  -H "Authorization: Bearer <access-token>"
```

## POST /auth/login

Authenticates an active user and returns an access token for protected endpoints.

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

### Response

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

## POST /auth/logout

Revokes the current access token. After logout, the same token can no longer be used for protected endpoints.

### Request

```bash
curl -X POST \
  "https://api.rylees.ai/v1/auth/logout" \
  -H "Authorization: Bearer <access-token>"
```
