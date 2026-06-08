# API Sub-project

@.claude/memory/MEMORY.md


## Scope
This directory contains the Rylees Backend API — a Laravel 13 / PHP 8.5 modular monolith.

Work ONLY in `src/api/` when changes belong to the backend API. Always use the `laravel-api` skill when working here.

## Tech Stack
- **Language**: PHP 8.5
- **Framework**: Laravel 13
- **Database**: PostgreSQL 16
- **Auth**: Laravel Sanctum (personal access tokens)
- **Email**: Laravel Mailable + configurable SMTP
- **LLM client**: `openai-php/client`
- **Testing**: Pest

## Modular Monolith Structure

```
app/Modules/
├── Auth/           # Registration, login, logout, email activation
├── Account/        # User profile and organisation management
├── Customer/       # Customer and contact management
├── Project/        # Project management, token generation
├── ReleaseHistory/ # Release note creation and retrieval
└── AI/             # LLM-based services (translation)
```

Each module MUST contain: `Controllers/`, `Models/`, `Services/`, `Requests/`, `Resources/`, `Repositories/`, `routes.php`.

## Key Rules
- All routes prefixed `/v1`; base URL `https://api.rylees.ai/v1`
- All responses `Content-Type: application/json`
- All timestamps ISO 8601 UTC
- All primary keys UUIDs
- Modules communicate only via public service class interfaces — direct cross-module Model access is PROHIBITED
- Raw SQL is PROHIBITED — use Eloquent or query builder
- All user input validated via Laravel Form Requests before business logic
- Soft deletes on all tables except `*_types` lookup tables
- `api_key` only exposed in `GET /users/me`, never in list responses
- `projects.token` only exposed in detail/create responses, never in lists

## Spec & Docs
- Related specification `/spec/SPEC.md`
- Full project specification: `/SPEC.md`. Read here when you need the big picture.
- Architecture docs: `/docs/architecture/`
- Design docs: `/docs/design/`

## Seed Data
Run `php artisan db:seed` to populate:
- `llm_tonality_types`: neutral, professional, friendly, humorous
- `llm_temperature_types`: precise (0.2), balanced (0.5), creative (0.8)
- `industry_types`: 13 records (Architecture → Other)
