<p align="center">
  <a href="https://github.com/getmilpa">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-dark.svg">
      <img src="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-light.svg" alt="Milpa" width="300">
    </picture>
  </a>
</p>

# Milpa Ops

> The system's metabolism for the Milpa PHP framework — security scanning, backup and restore,
> scheduled maintenance and bootstrap, as libraries a host drives.

[![CI](https://github.com/getmilpa/ops/actions/workflows/ci.yml/badge.svg)](https://github.com/getmilpa/ops/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/milpa/ops.svg)](https://packagist.org/packages/milpa/ops)
[![PHP](https://img.shields.io/badge/php-%E2%89%A5%208.3-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)

> **The Milpa system's metabolism** — security scanning, backup/restore, and scheduled
> maintenance for the Milpa PHP framework. Domain-blind and zero-Doctrine: three engine
> domains (Security, Backup, Cron) that any Milpa app gets identically, with the host
> injecting everything domain-specific through small interfaces and config files.

`milpa/ops` exists to answer one design question named by
[ADR-0023](../../.milpa/governance/adr/0023-ops-is-metabolism-not-domain.md): *"what belongs
to the system's metabolism, and what belongs to the domain?"* Metabolism is what keeps an
organism alive without being any of its functions — bootstrapping state, backing it up and
restoring it, watching its security, scheduling its maintenance. Every other Milpa package
names a physiological system (`governance` the law, `runtime` the heart, `http`/`template`
the senses and the voice, `auth` the identity, `plugin` the capacity to grow); `ops` names the
one that was missing.

## The three-question test (ADR-0023)

A capability belongs in `ops` only if **all three** of these are yes — any single **no** means
it's domain, and it belongs in a plugin or a feature package instead:

1. **Does *any* Milpa app need it, identically, without knowing its domain?** A CRM, a blog,
   and a SaaS use it the same way, with zero business-semantic configuration.
2. **Does it operate on the system as an artifact** — files, processes, the database as a
   blob, the maintenance calendar — **and never on business entities?** A backup doesn't know
   what a "lead" is; it compresses bytes.
3. **Does removing it leave the app functional but inoperable or unmaintainable** — instead of
   simply missing a feature? Without backup the app still runs, but no one responsible would
   operate it that way; without an email queue the app just loses a feature.

**The mechanism/content corollary** is the sharp edge that motivated the test: cron's
*scheduler* is metabolism — the registry, the due-matching, `cron:run` — but the *tasks
registered into it* are content, owned by whoever declares them (the host or a plugin, via its
provider). `ops` provides mechanisms; the domain provides content. The same line runs through
backup (the mechanism compresses; *which* extra directories to back up is the host's call) and
`security:scan` (the engine scans; the allowlists and permission policy are the host's).

## Install

```bash
composer require milpa/ops
```

## The three domains

Every engine class lives under `Milpa\Ops\{Security,Backup,Cron}\*`, takes what it needs by
constructor injection, and never reaches for Doctrine, a concrete container, or a host class.
Every report (`SecurityReport`, `BackupReport`, `CronReport`) implements the shared
`Milpa\Ops\Support\ReportInterface` — `toArray()` for JSON, `hasFindings()` to decide the exit
code — so a host command renders the same report as a table for humans or JSON for agents/CI
with one uniform shape.

### Security — `Milpa\Ops\Security\*`

| Class | Responsibility |
|-------|-----------------|
| `SecretScanner` | Scans a set of files line by line against regex rules (`id`, `pattern`, `severity`); an allowlisted substring on a line suppresses any match on it. Every finding's excerpt is redacted — the full secret is never echoed back. |
| `AuditParser` | Parses the JSON that `composer audit --format=json` produces into `AdvisoryFinding` records. The package never shells out itself — a host command runs the process and hands this parser its stdout. |
| `PermissionChecker` | Checks a list of `PermissionRule` (path, max octal mode, severity, label) against the real filesystem and returns a `PermissionFinding` for every path whose mode exceeds its declared maximum. |
| `SecurityReport` | Aggregates secrets + advisories + permissions into one `ReportInterface`. |

### Backup — `Milpa\Ops\Backup\*`

| Class | Responsibility |
|-------|-----------------|
| `BackupManager` | Orchestrates one backup run: a `DatabaseDumperInterface` dump plus a `StorageArchiver` archive, recorded as a `{id}.manifest.json`. `run()` never throws — a failed dump or archive comes back as a non-`ok` `BackupReport` instead, so a scheduled caller always has a report to act on. |
| `BackupCatalog` | Reads manifests back (`list()`) and enforces retention (`prune(int $keep)`), deleting the oldest backups beyond the keep count. |
| `StorageArchiver` | Packs a fixed list of host-declared paths into a `.tar.gz` (`PharData`). |
| `BackupManifest` | The JSON record of one backup: id, timestamp, db/storage file paths and byte counts. |
| `DatabaseDumperInterface` | **The contract the host implements.** `dump(string $destinationFile): void`, `restore(string $sourceFile): void`, `extension(): string`. The package ships no implementation — dumping a database is inherently driver-specific. |

### Cron — `Milpa\Ops\Cron\*`

| Class | Responsibility |
|-------|-----------------|
| `CronExpression` | Parses and evaluates a standard 5-field cron expression (`*`, `/step`, ranges, lists) with bounds validation at construction — an out-of-range field throws instead of silently never firing. |
| `TaskDefinition` | A named unit of scheduled work: `name`, the cron expression, a `Closure $callback`, and a description. `isDue(DateTimeImmutable $now)` answers "should this run now?" — it never invokes the callback itself. |
| `CronRegistry` | Holds every registered `TaskDefinition` and answers `due(DateTimeImmutable $now)`. |
| `CronRunner` | Runs every task due at a given instant, in isolation — a `Throwable` from one callback is caught, timed, recorded as a failing `TaskResult`, optionally logged, and never stops the runner from reaching the next due task. |
| `CronReport` | The aggregated result of one `cron:run` — every `TaskResult`, `hasFindings()` true if any task failed. |
| `CronProviderInterface` | **The contract a plugin implements** to contribute scheduled work: `cronTasks(): list<TaskDefinition>`. The cron-domain counterpart to `milpa/core`'s `ToolProviderInterface` — same shape, same idea. Called during registry assembly, not during plugin boot. |

## How the host injects content

ADR-0023 draws the line at "mechanism vs. content" — this package supplies the mechanism; the
host supplies everything domain-specific through three plain PHP config files plus two
interfaces it implements:

- **`config/security.php`** — `scan_paths` (dirs/files to scan for secrets), `rules` (regex +
  severity), `allowlist` (placeholder substrings that suppress a match), `permissions` (path +
  max octal mode + severity + label).
- **`config/backup.php`** — `dir` (where manifests/dumps/archives land), `storage_paths` (the
  *exact* list `StorageArchiver` packs into the `.tar.gz`, and the same list `backup:restore`
  re-reads as the allowed roots for its containment check), `keep` (retention count).
- **`config/cron.php`** — a `list<TaskDefinition>` the host declares directly. The host command
  also collects `cronTasks()` from every *booted* plugin implementing `CronProviderInterface`
  and merges both into one `CronRegistry` — host tasks first, then plugin tasks, in
  `getPlugins()` order.
- **`DatabaseDumperInterface`** — the host's implementation picks the dump strategy per driver:
  a raw file copy for SQLite, or `mysqldump`/`mysql` via `proc_open()` with an **argv array**
  (never a shell string) for MySQL — the password never touches argv, it reaches the child
  process exclusively through the `MYSQL_PWD` environment variable.
- **`CronProviderInterface`** — any plugin that wants scheduled work implements one method,
  `cronTasks(): array`, and builds/returns its `TaskDefinition` list; it never needs to touch
  `config/cron.php` or reach into the registry itself.

## The host commands

Three thin Symfony Console commands in the host (`src/app/Commands/`) assemble the engines from
config + injected implementations and expose them as `coa` subcommands — every one supports
`--json` for agents/CI, and follows the same exit-code convention as `coa:doctor` /
`coa:governance validate`: **exit 0 with no findings, non-zero the moment there's one to act
on.**

```bash
php coa security:scan [--json] [--skip-audit]   # secrets + composer audit + permissions
php coa backup:run [--json]                     # dump db + archive storage_paths + manifest + prune
php coa backup:list [--json]                    # read manifests back
php coa backup:restore <id> [--force] [--json]  # extract to an isolated temp dir, validate containment, THEN move
php coa cron:list [--json]                      # every registered task + its next due instant
php coa cron:run [--json]                       # run every task due right now
```

## Running cron: the canonical crontab line

`cron:run` does its own due-matching (`CronRegistry::due()` against the current instant) — the
crontab entry that drives it never changes when tasks are added, removed, or rescheduled in
`config/cron.php` or a plugin's `cronTasks()`. One line, once, per host deployment:

```
* * * * *  cd /ruta/al/host && php coa cron:run >> storage/logs/cron.log 2>&1
```

- **`* * * * *`** fires every minute — `cron:run` itself decides whether anything is actually
  due; a minute with nothing due is a fast no-op.
- **`cd /ruta/al/host &&`** — the host's config files are read relative to `HostPaths::root()`,
  so the command must run from the project root.
- **`>> storage/logs/cron.log 2>&1`** captures both stdout and stderr from every run, appended —
  a failing task's error and a crashed `cron:run` invocation both land in the same log.
- A non-zero exit (any task failed) is what a monitoring/alerting layer around the crontab
  entry should watch for — `cron:run` itself never retries a failed task.

## Requirements

- PHP **≥ 8.3**
- [`milpa/core`](https://packagist.org/packages/milpa/core) `*`
- [`psr/log`](https://packagist.org/packages/psr/log) `^3`

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security reports go through
[SECURITY.md](SECURITY.md) — privately, via GitHub Security Advisories.

## License

[Apache-2.0](LICENSE) © Rodrigo Vicente - TeamX Agency.

---

Milpa is designed, built, and maintained by **[Rodrigo Vicente - TeamX Agency](https://teamx.agency/?utm_source=github&utm_medium=readme&utm_campaign=milpa&utm_content=ops)**.
