---
name: feedback-laravel-quality
description: "After every Laravel feature or fix: write unit and integration tests, run Pint to lint the code"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 07a1ad29-ea2c-430e-bb6a-8693abaa2bfc
---

Always write tests and lint after any Laravel API work.

**Why:** User wants quality enforced consistently, not left as a separate step.

**How to apply:**
- Write unit tests for domain/application logic and integration (feature) tests for HTTP endpoints after implementing any feature or fix.
- Run `./vendor/bin/pint` before considering any Laravel task done.
- Tests live under `src/api/tests/`, Pint is in `require-dev`.
