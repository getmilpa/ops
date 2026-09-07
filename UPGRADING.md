# Upgrading

## 0.4.0 — the abandonment of 0.3.0 is reversed

`milpa/ops` was marked `abandoned` in 0.3.0. **That was wrong, and this undoes it.**

The measurement behind it was about the CRON engine, and it still holds: nothing in the framework implements
`CronProviderInterface`, nothing requires this package, and the family has never scheduled anything. What the
measurement could not see is what the package is FOR.

This is not a cron engine with extras. It is an operations toolkit for an agent: `Deploy` runs an ordered,
fail-fast step sequence whose steps own their own effects (`docker compose`, `coa` commands, HTTP probes);
`Security` scans for secrets, parses advisories and checks permissions; `Backup` catalogues and archives;
`Bootstrap` runs phased start-up. **The purpose is that an agent can operate a deployment** — and a purpose
lives with whoever holds the intent, not in a usage count.

Nothing about the code changed between 0.3.0 and 0.4.0. `abandoned` was metadata, and it is gone.

**What DOES stand from 0.3.0**, and is kept:

- The cron engine has no consumer today. That is a fact about the cron, not a verdict on this package.
- `CronRunner` now carries the test it never had: a task that throws does not stop the ones after it. Every
  previous test registered its thrower LAST, so a runner that stopped dead on the first failure passed all 33 —
  measured by mutating `run()` with a `break`. A promise no test can falsify is a comment.
