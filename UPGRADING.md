# Upgrading

## 0.3.0 — this package is abandoned

`milpa/ops` is marked `abandoned`. It is not deleted and its code still works: what changes is that the
framework stops OFFERING it as a capability.

**The measurement, not an opinion about the code.** The cron engine here is correct and its 34 tests pass in
0.02 s. That was never the question — demand was:

- **Zero implementors of `CronProviderInterface`**, ever, across the framework's 39 packages. The only class
  that ever implemented it was a test fake in an archived monorepo.
- **Zero packages require `milpa/ops`.** The apps that install it are lab apps that install everything.
- **Zero scheduled triggers** in the family's 161 CI workflows. This house has never scheduled anything.
- Nothing in the framework's roadmap, promises or work queue names periodic work.
- And the one place with the best claim to a cron — session expiry — **rejected one in writing**: *"it goes here
  and not in a cron because the moment it matters is this one. A nightly sweep would kill sessions nobody was
  watching, and leave alive the ones that were — the opposite of what is needed."*

The rest of the package is in the same state: all four contracts and nine classes are unwired. Adopting the
cron would have carried Security, Backup, Deploy and Bootstrap through the door with it, to serve zero named
tasks.

**Cheap to finish is not a need.** A built-and-unwired piece is debt that looks like capability precisely when
it is cheap and pretty (greenhouse `decisions/0213`, `decisions/0215` F6).

**If you were using it:** nothing breaks. `abandoned` is metadata — composer will tell you the package is no
longer maintained here. The engine is preserved, citable, and now carries the one test it never had: that a task
which throws does not stop the ones after it.
