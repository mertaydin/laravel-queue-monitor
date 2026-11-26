## v0.2.4 - 2026-11-26

- Fix failed-job payload inspection by looking up the selected job state instead of searching only the first 500 mixed-state jobs.
- Fix deletion of database failed jobs by UUID without comparing UUIDs against the integer jobs table primary key.
- Add regression coverage for failed-job detail payloads and UUID-based deletion.

## v0.2.3 - 2026-11-22

- Add `barryvdh/laravel-ide-helper` as a development dependency, selecting a compatible release for the supported Laravel versions.
- Run Laravel Pint across the PHP codebase and add an automated formatting workflow.

## v0.2.2 - 2026-11-19

- Fix stale-worker and failure-rate health alerts to dispatch through QueueAlertDispatcher.
- Add the shared security CI baseline: Composer audit, PHPStan/Larastan, changed-file Pint checks, PHP syntax validation, and automated tests.
- Add weekly Dependabot updates and resilient Composer dependency resolution.

## v0.2.1 - 2026-11-19

- Fix stale-worker and failure-rate health alerts to use the queue alert dispatcher instead of Laravel's console alert method.
- Add security CI with Composer vulnerability auditing, PHPStan/Larastan analysis, Pint checks, PHP syntax validation, and automated tests.
- Add weekly Dependabot updates for Composer dependencies and GitHub Actions.

# Changelog

## v0.1.29 - 2026-11-19

- Refresh the real queue dashboard and queue-comparison screenshots; align README screenshot documentation.


## v0.1.28 - 2026-11-19

- Recapture the current queue dashboard and cross-connection comparison screenshots from a temporary Laravel application.
- Keep the README screenshot references aligned with the current package UI.


## v0.1.27 - 2026-11-19

- Document force-refreshing published dashboard assets after package updates.

## v0.1.25 - 2026-11-19

- Document all six supported dashboard languages and Arabic right-to-left layout.

## v0.1.26 - 2026-11-19

- Update the README project status to accurately describe the published pre-1.0 package.
- Add direct links to changelog, releases, and issue support.
## v0.1.25 - 2026-11-19

- Patch release; retain the latest captured queue dashboard and comparison screenshots.


All notable changes to this project are documented here.

## Unreleased

## v0.1.24 - 2026-11-18

- Refresh the README with newly captured screenshots of the live queue dashboard and queue comparison pages.


## v0.1.23 - 2026-11-18

- Publish a patch release; no functional code changes.

## v0.1.22 - 2026-11-17

- Treat a missing PhpRedis extension as a Redis health-check warning instead of logging an application error with a full stack trace.

## v0.1.21 - 2026-11-17

- Normalize every dashboard value that may originate from driver, worker, history, request or session data before rendering it, preventing arrays from reaching Blade's HTML escaping layer.
- Place Explore jobs filters directly above the matching results and keep both in view after applying filters or navigating between result pages.
- Rework the Explore jobs filter layout into a responsive grid so controls and the Apply button stay within the dashboard at desktop, tablet and mobile widths.
- Move dashboard theme and language preference handling into published same-origin assets for strict Content Security Policy compatibility.

## [0.1.13] - 2026-11-17

- Fix dashboard theme and language selection on CSP-restricted deployments.

- Render the language selector directly in the top navigation beside the theme selector, without relying on JavaScript to relocate it.

- Make the dashboard authorization gate optional: access is allowed when the configured gate is not defined, and enforced across all dashboard routes when it is defined.

- Keep dashboard routes on the package's fixed `web` middleware instead of exposing middleware selection in package configuration.
- Document and test the configurable dashboard URL prefix, including nested paths and slash normalization.

## [0.1.0] - 2026-11-16

Initial pre-1.0 release.

- Monitor Database, Redis, Beanstalkd, Amazon SQS and Sync queue connections with driver-aware capabilities.
- Inspect queue jobs, payloads and failures; retry or delete jobs where the driver supports those operations.
- Record queue history and expose activity, duration and queue-health analytics.
- Track worker heartbeats and expose optional Supervisor XML-RPC process management.
- Configure payload masking, access control, alerts and queue health thresholds.
- Provide a responsive dashboard with job filtering, pagination and detail views.
- Include automated compatibility tests for supported PHP and Laravel versions, plus a live Supervisor daemon integration test.
