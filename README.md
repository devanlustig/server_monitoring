# Server Monitoring

A Laravel 12 foundation for server monitoring, backed by PostgreSQL and styled with Bootstrap 5.

## Local setup

1. Create a PostgreSQL database named `server_monitoring`.
2. Copy `.env.example` to `.env` and set the `DB_*` values for your local PostgreSQL instance.
3. Run `php artisan migrate`.
4. Run `npm install` and `npm run dev` while developing the frontend.

## Project structure

- `app/Http/Controllers` contains thin HTTP controllers.
- `app/Models` contains Eloquent persistence models.
- `app/Domain/Monitoring` contains monitoring-specific contracts and data objects. Add protocol probes here rather than to controllers.
- `database/migrations` contains the PostgreSQL schema, beginning with monitored server inventory.

## Monitoring schema

`monitored_servers` is the inventory of hosts. Each host has one or more
`monitoring_checks`, whose protocol-specific options live in `configuration`.
Each probe execution creates a `monitoring_check_results` time-series row.
`alert_rules` evaluate checks, while `incidents` and `incident_events` retain
the alert lifecycle and audit trail. The timestamp-based indexes support both
dashboard queries and retention jobs.

## CPU monitoring

Run `php artisan monitor:cpu` to collect a sample from each active server. The scheduler
registers this command every minute for every active SSH server; run `php artisan schedule:work` during
local development or configure a production cron task for `schedule:run`.
View the latest measurements at `/cpu`.

## Server authentication

Servers are managed at `/servers`. Password authentication uses phpseclib v3;
the username and password are tested before a server is saved. Both SSH
credentials use Laravel's encrypted casts, so the database stores ciphertext
rather than plaintext credentials. Connection implementations are selected by
`authentication_method`, allowing future methods to be added cleanly.

Each successful server save records the remote hostname, operating system,
kernel, CPU model/core count, total RAM, total disk, and successful connection
time. `RemoteCommandService` is shared by CPU monitoring and future RAM, disk,
and service collectors.
