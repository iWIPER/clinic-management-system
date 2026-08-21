# AGENTS.md

Rules for any AI agent (Claude Code, Codex, or other agent) working in this
repository. Complements, without duplicating, `CLAUDE.md` (specific
post-change UI/backend validation checklist) and the technical documentation
in `docs/`.

## Architecture

- **Stack:** Laravel 11 (PHP 8.3) + Inertia.js + Vue 3 (Composition API) +
  Tailwind CSS + PostgreSQL 16. Tests run against in-memory SQLite (Pest).

- **Multi-tenant:** tenant isolation is based on `clinic_id`.
  `App\Scopes\ClinicScope` (global scope, fail-closed — without
  `current_clinic_id` in the session, no records are returned) +
  `BelongsToClinic` trait on models. `EnsureCurrentClinic` middleware
  validates the active clinic and membership in `clinic_user` on every
  request.

- **Authorization:** Laravel Policies in `app/Policies/`. Two semantic
  patterns for nullable `clinic_id` exist in `app/Policies/Concerns/`:
  `AuthorizesClinicOwnership` (null never matches — the resource always
  belongs to a clinic) and `AllowsGlobalOrOwnClinic` (null always matches —
  global/system resource, e.g. default categories/templates).

- **Layers:** Controllers (`app/Http/Controllers`) remain thin. Non-trivial
  business logic belongs in `app/Services/`. Cross-tenant validation using
  `exists:` / `Rule::exists()` must always be scoped by `clinic_id`
  (these rules ignore global scopes by default — see existing controller
  examples before writing a new one).

- **System Admin / clinic context:** System Admin is a global platform
  privilege, separate from the clinic context.
  Being a System Admin does not imply automatic clinical access.
  A user may be both a System Admin and a member of a clinic, but these
  contexts must remain explicitly separated.
  The `/admin` Backoffice is a separate shell from the clinic shell.
  System Admin users enter the Backoffice context by default.
  Clinic functionality must only be accessed through an explicit clinic
  context and must continue to respect tenant isolation, RBAC, and Policies.

- **Public invites/tokens:** strong entropy (`Str::random(32)`), maximum
  validity and revocation are enforced as backend business rules, never only
  on the frontend.

Do not invent new architectural patterns. If an existing screen or flow
already solves something in a particular way, follow the existing approach
instead of introducing a second way to do the same thing.

## Security

- Preserve tenant isolation in every change — never remove or weaken
  `ClinicScope`, `BelongsToClinic`, or an existing `clinic_id` scope.

- Never bypass or circumvent existing authorization Policies.

- Never expose secrets, tokens, or credentials — not in code, commits,
  responses, or reports. `.env` files must never be committed.

- Never put credentials or secrets in the frontend.

- Always validate on the backend, even when client-side validation already
  exists.

- Consider LGPD when handling personal or sensitive data (see
  `docs/LGPD_ARQUITETURA.md`).

- Do not remove security protections "to make development easier".

- Do not disable or skip security tests just to make the test suite pass.

## Database

- Never run destructive commands (`migrate:fresh`, `migrate:reset`,
  `db:wipe`, mass `DELETE` / `TRUNCATE`) without explicit user
  authorization, even in a local environment.

- Structural changes must follow the migration strategy already used by the
  project (one migration per change, named with date/description).

- Do not modify benchmark data
  (`database/seeders/BenchmarkSeeder.php`) without a real need, and never
  automatically re-run this seeder.

- Clearly distinguish development data, benchmark data
  (synthetic, locally generated), and production data. Never treat one as
  another.

## Development

- Understand the existing implementation before modifying it.

- Look for reuse before creating duplication (existing helper, composable,
  or Service).

- Preserve established patterns, even when they are not the approach you
  would choose from scratch.

- Avoid refactoring unrelated to the requested task.

- Do not fix "extra" issues outside the requested scope — record them as
  recommendations instead of modifying them.

- Do not add new dependencies without explaining why the native or already
  installed alternative is insufficient.

## Testing

- Every relevant change should include a new or updated test when
  appropriate, and related tests must be executed before reporting the task
  as complete.

- UI, scheduling, and form changes must follow the specific checklist
  defined in `CLAUDE.md` (including build, authenticated visual testing, and
  screenshots — not duplicated here).

- Report the actual test/build results — never claim success without
  actually running the relevant checks.

## Git / GitHub Workflow

- The main production branch is `master`.

- Do not develop directly on `master`.

- Every relevant change must be linked to a GitHub Issue.

- The standard workflow is:

  `Issue → branch → implementation → tests → Pull Request → CI → QA → merge into master`

- Every Pull Request must reference the related Issue.

- Prefer `Closes #<issue>` when merging the Pull Request should close the
  related Issue.

- Pull Requests may only be merged when the required CI checks have passed:
  - Backend (PHP)
  - Frontend (Vite)

- Do not bypass branch protection.

- Do not force-push to `master`.

- Never add secrets or credentials to the repository.

## Branch Naming

- `feature/<name>` for new functionality.

- `fix/<name>` for bug fixes.

- `improvement/<name>` for improvements or refinements.

- `security/<name>` for security hardening or security fixes.

- `chore/<name>` for maintenance and infrastructure work.

## Production / Deployment

- Production deployment requires green CI and completed QA.

- Do not execute deployment automatically as a consequence of a local
  commit.

- Production deployment is a separate, explicitly authorized step.

- Secrets and credentials must remain outside Git.

- No agent may deploy to production, modify AWS infrastructure, or change
  production resources without explicit and direct user authorization for
  that specific action.

## System Admin / Clinic Context

- System Admin is a global platform privilege, separate from the clinic
  context.

- Being a System Admin does not automatically grant unrestricted access to
  clinic functionality.

- A user may be both a System Admin and a member of a clinic.

- System Admin and clinic contexts must never be mixed within the same
  navigation shell.

- The `/admin` Backoffice is a separate application context and shell from
  the clinic application.

- System Admin users enter the Backoffice context by default.

- The Backoffice must expose only system-level functionality, such as
  platform administration, clinics, users, plans, logs, exports, and other
  existing System Admin functionality.

- Clinic functionality such as appointments, patients, consultations,
  procedures, inventory, documents, billing, team management, and clinic
  settings belongs to the clinic context.

- Clinic functionality must not appear in the System Admin navigation.

- A System Admin who is also a clinic member may access a clinic only through
  an explicit clinic context.

- Entering a clinic context must preserve the existing clinic membership,
  tenant isolation, RBAC, and Policies.

- Returning from a clinic context to the Backoffice must be explicit and
  must restore the System Admin shell.

- Do not grant unrestricted clinic access merely because a user has the
  System Admin privilege.

- Do not remove or alter a user's clinic membership when granting or
  revoking System Admin privileges.

## Pull Requests

Every relevant Pull Request must:

- reference the related Issue;
- explain the problem;
- explain the solution;
- report the tests that were run;
- report relevant impacts;
- report known limitations or follow-ups.

When applicable, use `Closes #<number>` to close the Issue when the PR is
merged.

A Pull Request is not a code dump — it is the review and integration
mechanism, not the deployment mechanism.

## Deploy

A Pull Request merged into `master` does **not** automatically trigger
deployment.

Deployment is a separate, controlled step using the infrastructure in
`infra/` (Terraform + AWS).

No agent may execute deployment, modify AWS infrastructure, or change
production without explicit and direct user authorization for that specific
action.

## AI Agents

The agent must not:

- rewrite large parts of the system without a real need;
- create generic abstractions without a concrete existing use case;
- create duplicate components when an existing component already solves the
  problem;
- add libraries for problems that can be solved with simple existing code;
- generate excessive or redundant documentation;
- add animations, skeletons, lazy loading, or loaders by default without a
  real and perceptible user need (see `docs/` if more specific motion/UI
  guidelines exist);
- turn a small change into a new architecture.

The agent should prefer:

- simple, explicit, testable code;
- consistency with existing project patterns;
- reuse of existing services/components/helpers;
- minimal changes within the requested scope;
- code that is easy for another human developer to understand and maintain.
