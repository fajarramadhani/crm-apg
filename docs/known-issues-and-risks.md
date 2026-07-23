# Known Issues and Risk Register

| Category | Description | Impact | Likelihood | Mitigation | Owner | Target date | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Security | Antivirus/malware scanning for private evidence is not integrated. | Hostile files may be downloaded by authorized users. | Medium | Require infrastructure scanner/quarantine before production. | `[TBD]` | Before production | Open |
| Infrastructure | Hosting, domains, secret manager, durable private storage, and central monitoring are not selected. | Deployment cannot meet production controls. | High | Approve target architecture and complete staging deployment. | `[TBD]` | Before staging | Open |
| Performance | Main JS chunk is about 946 kB minified; route code splitting is pending. | Slower initial load on constrained networks. | Medium | Add route-level lazy loading and staging bundle budget. | `[TBD]` | Before production | Open |
| Performance | Ticket resources and scanners contain N+1 candidates; reports materialize rows in PHP. | Latency/memory may exceed targets at scale. | Medium | Query-count benchmarks, eager counts, SQL aggregation, bounded candidates. | `[TBD]` | Before production scale approval | Open |
| Usability | Exhaustive all-role tablet/mobile browser UAT remains pending. | Undetected workflow or accessibility regression. | Medium | Execute human checklist and retest defects. | `[TBD]` | Before production | Open |
| Operational | Scheduler heartbeat and restore drill have no staging evidence. | Silent scanner outage or failed recovery. | High | Configure monitors and perform quarterly restore drill. | `[TBD]` | Before production | Open |
| Dependency | No PHPStan/Larastan or frontend unit-test suite is configured. | Some defects rely on runtime tests to detect. | Medium | Add incrementally after release without blocking current toolchain. | `[TBD]` | Post-staging | Accepted gap |
| Data | Deployment/rollback schema-service compatibility requires production-engine integration tests. | MySQL behavior may differ from SQLite. | Medium | Add disposable MySQL CI and cross-ticket integrity tests. | `[TBD]` | Before production | Open |
| Compliance | Retention, privacy classification, and evidence deletion policy are not approved. | Regulatory/contractual exposure. | Medium | Obtain legal/security policy and implement lifecycle controls. | `[TBD]` | Before production | Open |

No high or critical dependency advisory remained after the Phase 16 lockfile updates. Open risks above must not be marked closed without staging or human evidence.
