# DevArt Repository Standards

# Mandatory AI Roles

This repository uses a strict multi-agent workflow.

## Cursor — Sole Implementation Agent

Cursor is the ONLY AI agent authorized to modify repository files.

Cursor may:
- edit source code
- create or delete repository files when required
- modify tests, documentation, manifests, SQL and build scripts
- implement approved fixes and features

All implementation changes must be performed exclusively by Cursor.

## Devin — Review Only

Devin is strictly an independent reviewer and auditor.

Devin may:
- inspect the repository
- run reviews and tests
- perform security, performance and compatibility audits
- identify bugs, regressions and architectural risks
- recommend exact corrections

Devin MUST NOT:
- modify repository files
- apply fixes
- create implementation commits
- create code-changing pull requests
- silently correct findings

All Devin findings must be returned for discussion and implementation by Cursor.

## ChatGPT / OpenAI — Review and Architecture Only

ChatGPT, OpenAI models, Claude and Kimi are strictly advisory for this repository.

ChatGPT may:
- inspect and review repository content
- review Cursor implementations
- discuss architecture and strategy
- investigate problems
- perform security, performance and compatibility reviews
- prepare implementation instructions for Cursor
- review Devin findings

ChatGPT, OpenAI models, Claude and Kimi MUST NOT:
- modify repository files
- apply patches
- create implementation commits
- create or alter release artifacts
- silently implement recommended fixes

All implementation changes recommended by ChatGPT must be performed by Cursor.

## Authority Rule

These role restrictions are mandatory and override task-specific prompts.

Even if Devin, ChatGPT, Claude or Kimi is asked to "fix", "implement", "change", or "correct"
something, they must only analyze the request and provide recommendations.

Repository implementation ownership always remains exclusively with Cursor.
## Project

This repository is part of the DevArt Joomla extension family.

Read this file before making any change.


## DevArt QA Workflow

This repository uses the DevArt QA Platform for development and release validation.

QA harness path (convention):

`/Users/kostas/Desktop/SITES/files/joomla6_tests_qa`

This product's primary component id: `com_devartsupport`

Classification labels and triage guidance live in the harness:

`/Users/kostas/Desktop/SITES/files/joomla6_tests_qa/docs/QA-TRIAGE.md`

The QA harness is maintained separately and MUST NOT be modified from this
product repository unless the user explicitly opens a separate QA-framework task.

Cursor may **execute** QA npm scripts in the harness path (read/execute only).
Cursor MUST NOT modify harness source, tests, generated specs, adapters,
allowlists, safety gates, or cleanup rules from a product task.

If this component has no harness profile yet, report `NOT COVERED` and STOP
rather than inventing suites.

### Development QA

After implementing a requested product change, Cursor must run the relevant
DevArt QA component checks for `com_devartsupport`.


**Critical — two local Joomla sites are not the same target:**

| Site | Path | URL | Role |
|---|---|---|---|
| Herd smoke | `/Users/kostas/Desktop/SITES/files/demo3` | `https://demo3.test` | Install + HTTP smoke only |
| QA harness | `/Users/kostas/Desktop/SITES/files/joomla6_tests` | `https://joomla6_tests.test` | Cypress / `qa:component` / generated specs |

The harness primary target is defined in:

`/Users/kostas/Desktop/SITES/files/joomla6_tests_qa/config/targets/joomla6.json`

(`baseUrl` = `https://joomla6_tests.test`).

**Before any harness Cypress / `test:generated` / `qa:component` run:**

1. Install the **same** generated ZIP on `joomla6_tests` with Joomla CLI:

```
php /Users/kostas/Desktop/SITES/files/joomla6_tests/cli/joomla.php extension:install --path=/absolute/path/to/package.zip
```

2. Verify the installed package/component version matches the artifact under test
   (e.g. query `#__extensions` `manifest_cache` for this product's `pkg_*` / `com_*`).

3. Only then run harness npm scripts.

Installing only on `demo3` and then running Cypress is a **false PASS**: the harness
does not exercise `demo3`. Never report harness QA as validation of a new package
unless that package is confirmed installed on `joomla6_tests`.

Default ladder:

1. install ZIP on `demo3` (smoke) **and** on `joomla6_tests` (harness)
2. the directly affected curated/generated test when known
3. otherwise `npm run test:generated -- com_devartsupport` (from the harness path),
   plus this component's FE / settings / ACL suites when that profile applies
4. after a green product auto-fix: complete component profile via
   `npm run qa:component -- com_devartsupport` (or the equivalent full component set)

Every QA failure must be classified before changing product source.

Cursor may automatically fix and retest only when ALL are true:

- the failure is classified as a PRODUCT_DEFECT
- the classification is supported by concrete evidence
- the defect belongs to this repository
- the failure is not caused by the QA framework, environment, safety system,
  ownership rules, leaks, recovery, invariant drift or ambiguous evidence

If the cause is ambiguous, STOP and report.

### Product-fix restrictions

While fixing a product defect, Cursor MUST NOT obtain PASS by modifying:

- QA tests
- generated QA specs
- expectations
- QA adapters
- fixtures
- eligibility rules
- safety gates
- cleanup rules
- error allowlists
- the QA framework itself

Do not weaken an assertion to make a product defect pass.

A suspected QA-framework defect is a separate task and must be reported instead
of silently corrected from this repository.

### Auto-fix limit

Cursor may make at most two automatic fix attempts for the same classified
failure.

If the failure remains after two evidence-based attempts:

STOP and report the evidence and attempted fixes.

### Verification after a fix

After a product fix:

1. rerun the directly affected test or scenario
2. if green, rerun the complete QA profile for this component
3. if shared DevArt code, a shared plugin, library or package was changed,
   run the appropriate cross-component regression
4. confirm zero unresolved:
   - fixture leaks
   - configuration leaks
   - recovery entries
   - invariant drift
   - unexpected non-QA state changes

A green process exit code alone is not sufficient if QA reports contain
unresolved safety findings.

### Release QA

Release QA validates the actual release artifact, not merely the current source tree.

From the harness path:

`npm run qa:release:artifact -- --component com_devartsupport [--package /path/to/pkg_….zip]`

A failed artifact must never be repaired in place or treated as PASS.

Release flow:

FAIL
→ fix source in this repository
→ build a NEW artifact
→ restart full release QA against the new artifact

A valid release PASS must be tied to the tested artifact and record:

- package/component
- package version
- artifact SHA-256
- installed version
- targetId
- runId

Do not reuse a PASS from an older artifact after source changes.

### Crash recovery

If this extension causes a Joomla bootstrap/site failure:

- the product result remains FAIL
- environment recovery is reported separately
- successful recovery does not convert the product result to PASS

A plugin disabled by emergency recovery must not be automatically re-enabled by
the recovery mechanism.

After the source defect is corrected, re-enable/retest it only through an
explicit normal QA step.

### Repository boundaries

Cursor may modify this repository because it is the authorized product workspace.

Cursor MUST NOT modify:

- the DevArt QA framework
- another DevArt product repository
- Joomla core
- Joomla configuration.php

unless the user explicitly authorizes a separate task for that workspace.

### External actions

Do not commit, push, tag, publish a release or change remote state unless the
user explicitly requests or authorizes it.

### Agent roles

The Mandatory AI Roles defined above remain in force.

Cursor is the sole implementation agent.
Devin, ChatGPT/OpenAI, Claude and Kimi remain review-only where configured.

### Short prompts (operator)

- `Dev QA this component`
- `Implement X. Then run development QA for this component.`
- `Fix from QA failure — classify first, max 2 auto-fix attempts.`
- `Release QA this package <path-to.zip>`

Harness reminder: Cypress runs on `joomla6_tests.test`, not `demo3.test`.
Always install the ZIP on `joomla6_tests` before claiming harness PASS.

---

# Communication

- Always communicate with the user in **Greek**, unless the user explicitly requests another language.
- Source code, comments, manifests, README, CHANGELOG and all repository documentation remain in **English**.

---

# Supported Platform

- Joomla 6+
- PHP 8.3+
- Modern MariaDB/MySQL
- No Joomla 3/4/5 compatibility
- No PHP 7.x or PHP 8.0–8.2 compatibility
- No backward compatibility.
- No deprecated Joomla APIs.

# Official Documentation

When implementation depends on Joomla or PHP APIs, behavior, compatibility,
deprecations, configuration, or supported syntax, verify the solution against
the current official documentation.

Preferred authoritative sources:

- Joomla Manual / official Joomla documentation
- Joomla API documentation
- Joomla GitHub repositories when implementation details must be verified
- PHP official documentation at php.net

Rules:

- Prefer current official documentation over tutorials, blog posts, forum answers,
  Stack Overflow answers, generated examples, or remembered patterns.
- Do not copy legacy Joomla patterns from Joomla 3, 4 or 5 unless they are explicitly
  confirmed as current and appropriate for Joomla 6+.
- Do not introduce deprecated Joomla or PHP APIs.
- If the official documentation and the existing repository implementation differ,
  investigate the reason before changing the implementation.
- Do not replace a proven repository implementation merely because another pattern
  exists in the documentation.
- When uncertain about an API, verify it instead of guessing.

---


# Core Principles

Always prioritize:

1. Stability
2. Simplicity
3. Security
4. Joomla-native architecture
5. Update safety
6. Performance
7. Maintainability

Prefer the **smallest safe solution**.

Never introduce unnecessary complexity.

---

# Repository Philosophy

The repository is the **single source of truth**.

All development happens only inside:

```
source/
```

Never use:

- builds/
- releases/
- archive/
- extracted ZIP packages

for development.

Generated packages are disposable.

If a generated package differs from the repository,
the repository is always considered correct.

---

# Repository Structure

```
source/
builds/
releases/
archive/
docs/
qa/
scripts/
```

Only `source/` contains editable source code.
Historical ZIP checkpoints are not kept under `archive/`; that directory retains only `.gitkeep`.


---

# Working Method

Before every task:

1. Read `AGENTS.md`.
2. Read `PROJECT_STATUS.md`.
3. Read the relevant documentation under `docs/`.
4. Inspect the existing implementation.
5. Explain the planned change.
6. Identify the files that will be modified.

Rules:

- Never modify unrelated files.
- Never perform unnecessary refactoring.
- Always implement the smallest safe change.
- If something is unclear, ask before implementing.

---

# Standard Development Cycle

Every milestone follows exactly this workflow:

```
Read
↓

Plan
↓

Implement

↓

Validate

↓

Build installable package

↓

Cursor installs the ZIP on local Herd smoke (`https://demo3.test`)
and on the harness site (`https://joomla6_tests.test`)

↓

Cursor local smoke QA

↓

STOP

↓

User performs VPS Joomla QA

↓

If successful

↓

Update PROJECT_STATUS.md

↓

Next milestone
```

One installable package = One milestone.

Never continue automatically to the next milestone.

---

# Local Herd Joomla vs VPS QA

The repository `source/` is the only editable source of truth.

Local Joomla sites are **runtime test targets**, not development trees:

- Smoke site path: `/Users/kostas/Desktop/SITES/files/demo3`
- Smoke URL: `https://demo3.test`
- Smoke administrator URL: `https://demo3.test/administrator?eisodos`
- Harness site path: `/Users/kostas/Desktop/SITES/files/joomla6_tests`
- Harness URL: `https://joomla6_tests.test` (Cypress / DevArt QA Platform target)
- VPS QA site: `https://dev.devart.gr/demo3`

After every new installable package, Cursor MUST:

1. Install the generated ZIP on **both** local sites with Joomla CLI:

```
php /Users/kostas/Desktop/SITES/files/demo3/cli/joomla.php extension:install --path=/absolute/path/to/package.zip
php /Users/kostas/Desktop/SITES/files/joomla6_tests/cli/joomla.php extension:install --path=/absolute/path/to/package.zip
```

2. Confirm harness-site installed version matches the ZIP under test before Cypress.
3. Run local smoke QA on frontend and administrator of `demo3` (admin URLs must include `?eisodos`).
3a. Run generic HTTP rendering smoke checks (template A):
    - `GET https://demo3.test/`
    - `GET https://demo3.test/administrator?eisodos`
    - Use `curl -L` and PASS only if both responses return HTTP 2xx/3xx and the response body does not contain `Fatal error` or `Joomla\CMS\Error`.
4. Run harness Development QA against `joomla6_tests` (see DevArt QA Workflow above).
5. Report the result and the ZIP path to the user.
6. STOP and wait for the user to install and test the **same ZIP** on the VPS.

Rules:

- Never edit installed files inside the local Joomla tree as source of truth.
- Never copy `source/` files into the Joomla site by hand.
- Always install the generated ZIP, because that is the same path the VPS will use.
- Never treat Cypress PASS on an older installed version as validation of a newer ZIP.
- Local Herd install and smoke QA do **not** close the milestone.
- `PROJECT_STATUS.md` is updated only after the user confirms successful VPS QA.
- Work on a component from its repository workspace, not from the `demo3` or
  `joomla6_tests` site workspace.

---

# Mandatory QA Question

If the current task generates a new installable package,
the agent MUST install it on **both** local sites (`demo3` and `joomla6_tests`),
complete smoke + harness QA against the matching installed version, then STOP.

Before continuing, always ask:

> Έγινε η δοκιμή στο VPS;
> Είναι όλα σωστά ώστε να κλείσουμε αυτό το milestone και να ενημερώσω το PROJECT_STATUS.md;

Do not skip this question.

Do not continue with another milestone until the user answers.

---

# PROJECT_STATUS.md

`PROJECT_STATUS.md` is the operational summary of the repository.

Read it before every task.

Update it **only** after the user explicitly confirms that the generated package was successfully installed and tested on the VPS Joomla site.

Never update it:

- after planning
- after editing code
- after validation only
- after building only
- after local Herd install only
- after local smoke QA only

Record only verified information.

Never invent:

- versions
- build numbers
- release status
- verification results

Keep the file short and practical.

---

# Validation

Before building:

- PHP syntax
- XML validation
- Manifest validation
- Manifest references
- Version consistency
- Diff review (only expected files changed; no debug remnants; no accidental local paths; no generated artifacts)
- Secure-by-default code review (validate inputs, escape outputs, enforce permissions, safe error handling)

Then:

- Build installable package
- Generate SHA-256

Never claim Joomla compatibility without a real Joomla installation test.
Local Herd smoke QA is required. User VPS QA is required before closing the milestone.

---

# Coding Rules

- Use Joomla-native APIs.
- Use Dependency Injection where appropriate.
- Use WebAssetManager where appropriate.
- Use typed PHP.
- Use strict types.
- Avoid global state.
- Avoid unnecessary dependencies.
- Prefer CSS over JavaScript whenever possible.
- Prefer native JavaScript.
- Never introduce jQuery or external frontend frameworks unless explicitly required.

---

# Performance

Design for:

- very large Joomla websites
- high traffic
- Cloudflare
- Full Page Cache
- shared hosting
- enterprise servers

Avoid:

- unnecessary database queries
- unnecessary filesystem scans
- unnecessary remote requests

---

# Security

Never expose filesystem paths.

Always use:

Controller → Service → Storage

Always enforce access before serving content.

Validate every input.

Escape every output.

---

# UI

Use Joomla-native administrator UI.

Use the standard DevArt themes:

- Red
- Orange
- Blue
- Green
- Yellow
- Gray
- Dark

Only the active submenu uses the DevArt active color.

---

# Build Rules

All generated packages must:

- install correctly
- update correctly
- include every required extension
- generate SHA-256
- never overwrite an existing build

---

# Repository Infrastructure Changes

Changes affecting:

- scripts/
- manifests under `source/`
- repository structure
- packaging
- validation

must always be:

- validated
- built

A Joomla QA is required only if a new installable package is generated.

Do not update `PROJECT_STATUS.md`
until the user confirms successful Joomla testing.

---

# Documentation-only Changes

If a task modifies only:

- AGENTS.md
- PROJECT_STATUS.md
- README.md
- CHANGELOG.md
- docs/
- qa/

and does not modify:

- source/
- manifests under `source/`
- scripts/
- generated package contents

then:

- Do not run validation unless explicitly requested.
- Do not build a new installable package.
- Do not request Joomla QA.
- Review the documentation changes and stop.

If a new installable package is generated,
this workflow no longer applies.

Follow the Standard Development Cycle.

---

# Decision Rules

If something is unclear:

- Ask.
- Do not guess.
- Do not silently change architecture.
- Do not implement assumptions.

When in doubt,
choose the simplest safe solution.

---

# Model Workflow

Implementation is performed exclusively by Cursor.

Other AI models may be used only for:

- review
- validation
- testing
- security audit
- performance audit
- architecture discussion
- strategy
- implementation recommendations

They must never modify repository files.
---

# Project-Specific Rules

## DevArt Support

- This product is a Joomla 6 ticket desk: registered users open and reply on the
  **site portal**; staff manage and reply only in the **administrator**.
- Frontend portal access defaults to Registered via menu Access Level and
  component ACL (`core.create` / `core.edit`). Narrow to another group when
  needed (e.g. subscriptions) through Joomla ACL/Access only — no custom path UI.
- Do not add guest tickets, frontend attachments, assignment, modules, live chat,
  or a public unauthenticated portal without a dedicated milestone.
- Keep the product simpler than Akeeba Ticket System. Use `temp/` as reference
  only, never as source.
- Ticket data is private. Enforce login, ACL and ownership before serving any
  frontend ticket content.
- Packaged languages: `en-GB`, `el-GR`, `fr-FR`, `de-DE`, `es-ES`, `it-IT`,
  `pt-BR`, `cs-CZ`, `nl-NL`, `pl-PL`, `ru-RU`, `uk-UA`, `ja-JP`, `tr-TR`,
  `zh-CN`. English is the source of truth. Do not translate keys or format
  specifiers (`%s`, `%d`, `%1$s`, `{name}`).
- After every installable package: install and test on local Herd
  (`https://demo3.test`), then give the user a concrete VPS checklist for the
  same ZIP. Do not close the milestone until the user confirms that checklist.
- Product versions stay on `1.0.x` until an explicit final `1.1.0` release. Do
  not jump to `1.1.0` for intermediate milestones.