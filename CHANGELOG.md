# Changelog

All notable changes to this project are documented here.

## Unreleased

- Make the dashboard authorization gate optional: access is allowed when the configured gate is not defined, and enforced across all dashboard routes when it is defined.

- Allow configuring dashboard route middleware through `queue-monitor.middleware`, while keeping `web` as the default.
- Document and test custom middleware such as `super-admin` and `super-ip` on all dashboard routes.
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
