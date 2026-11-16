# Security

Queue Monitor can expose job payloads and control background workers. Restrict access carefully.

## Dashboard access

Define the `viewQueueMonitor` gate. The dashboard is denied when the gate does not grant access.

Define `manageQueueWorkers` separately. This gate protects Supervisor process controls.

## Request protection

All process actions use POST requests. Laravel's web middleware applies CSRF protection. Actions are validated against the configured Supervisor server and program allowlist.

## Payload masking

Sensitive payload keys are masked by default. The default list includes passwords, tokens, API keys and authorization values. Add application-specific keys in `payload.sensitive_keys`.

Do not disable masking on shared or production dashboards unless access is tightly restricted.

## Supervisor credentials

Store credentials in environment variables. Use HTTPS and verify certificates. Do not expose Supervisor's XML-RPC endpoint to the public internet.

Only list programs that operators may control. Wildcard patterns expand the allowlist.

## Webhook notifications

Notifications are disabled by default. Store webhook URLs in environment variables. Do not accept webhook URLs from dashboard input.

Webhook payloads include job identifiers and exception details. Treat them as operational data.

## Worker history

Worker heartbeats and job history are stored in package tables. Apply your normal database backup and retention policies. Limit access to these tables.
