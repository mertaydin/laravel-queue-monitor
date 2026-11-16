# Queue drivers

The package registers supported Laravel queue connections automatically.

| Driver | Queue metrics | Job details | Retry / delete |
|---|---|---|---|
| Database | Pending, reserved, failed | Pending, reserved, failed; completed with history | Supported |
| Redis | Pending, reserved, delayed | Pending and reserved; completed with history | Supported for visible/reserved payloads |
| Beanstalkd | Ready, reserved, delayed, buried | One peekable job per state and tube; completed with history | Supported |
| Amazon SQS | Pending, in-flight, delayed | Completed history only | Not supported |
| Sync | No queue depth | Completed and failed history | Not supported |

## Database

The database driver reads Laravel's `jobs` and `failed_jobs` tables. Completed jobs need package history.

## Redis

Configure queue names in `queue-monitor.connections.{connection}.queues`. The adapter does not scan Redis keys. Failed jobs are stored by Laravel's failed-job provider. They are not read from Redis.

## Beanstalkd

Install the optional client:

```bash
composer require pda/pheanstalk:^5.0
```

Beanstalkd can count reserved jobs. It cannot list their payloads. The protocol also exposes only the next ready, delayed or buried job for inspection.

## Amazon SQS

Install the AWS SDK in the host application:

```bash
composer require aws/aws-sdk-php
```

Configure queue names in `queue-monitor.connections.{connection}.queues`. Standard and FIFO queues are supported for metrics.

The dashboard does not receive messages to inspect them. A receive operation changes message delivery counts. It can also trigger a redrive policy. SQS retry and delete actions need a live receipt handle. The package does not retain these handles.

## Sync

The sync driver runs jobs immediately. It has no pending queue. Enable history to inspect completed and failed jobs after execution.
