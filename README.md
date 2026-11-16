# Laravel Queue Monitor

A Laravel package for monitoring and managing queues across supported queue connections.

> **Status:** Early development. APIs and configuration may change.

## Goals

- Monitor pending, running, completed, and failed jobs where the queue driver exposes the relevant information.
- Inspect job payloads with readable JSON, search, and configurable sensitive-value masking.
- Inspect failed-job exceptions and stack traces.
- Retry and delete failed jobs, including guarded bulk actions.
- Track throughput, execution duration, and success/failure trends.
- Support multiple queue connections through driver-specific adapters.
- Optionally record job lifecycle events so completed-job history and historical charts remain available after the underlying queue driver removes jobs.

## Design principles

- **Capability-aware:** Driver-specific limitations are explicit; the package never presents unsupported data as if it were available.
- **History-aware:** Completed-job history is opt-in and stored by the package when enabled.
- **Secure by default:** Dashboard access is protected by an authorization gate, mutating actions use Laravel's normal web protections, and sensitive payload values can be masked.
- **Laravel-native:** Integrates with Laravel's queue events, configuration, service container, and conventions.
- **Maintainable:** Small contracts, focused adapters, clear naming, and automated tests.

## Initial driver targets

- Database
- Redis
- Beanstalkd
- Amazon SQS (including FIFO where supported by the underlying integration)
- Sync (event-based visibility; no persistent queue depth)

Each driver will document and test its actual capabilities.

## Development

Installation instructions, configuration, supported-version matrix, and usage documentation will be added as the package takes shape.

## License

MIT
