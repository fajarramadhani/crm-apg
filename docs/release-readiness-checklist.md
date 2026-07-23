# Release Readiness Checklist

- [ ] Approved immutable commit and release candidate.
- [ ] Backend tests, Pint, Composer validation/audit pass.
- [ ] Frontend frozen install, typecheck, format, build, and audit pass.
- [ ] Security and authorization review approved; no open high/critical issue.
- [ ] Migration fresh/rollback/reapply verified on disposable database.
- [ ] Backup and staging restore drill verified.
- [ ] Rollback owner, trigger, and procedure approved.
- [ ] Production environment guard, secrets manager, domain, HTTPS, CORS, Sanctum, and cookie settings verified.
- [ ] Scheduler and optional queue supervision verified.
- [ ] Durable private storage and malware-scanning control approved.
- [ ] Liveness/readiness and smoke tests pass.
- [ ] Performance targets validated in staging, not inferred from local results.
- [ ] Desktop/tablet/mobile UAT complete for all eight roles.
- [ ] Business UAT sign-off and IT release approval recorded.
- [ ] Release communication and monitoring observation window approved.
- [ ] Post-release support, database, backup, scheduler, and monitoring owners assigned.

Unchecked items block `READY FOR PRODUCTION`.
