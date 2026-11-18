# Changelog

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
