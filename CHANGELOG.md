# Changelog

All notable changes to Course Captain (Free) are documented here. Versions follow SemVer (R0-35 §3.2). Required markers for releases: `Schema`, `Backfill`, `Destruktiv (Contract) – Backup vorher`, `Sicherheitsupdate:` (R0-18 §3.1).

## 1.0.0-dev – unreleased

- Requirements raised (MINVER-1, 17.11.2026): PHP 8.5, WordPress 7.0, recommended MariaDB 12.3 / MySQL 9.7; older database servers from MariaDB 10.6 / MySQL 8.0 still work with a system status warning.
- Plugin core (R1-01): requirements check (PHP 8.3, WordPress 7.0, MariaDB 10.6 / MySQL 8.0 with InnoDB, JSON and utf8mb4), activation per site, network activation blocked, hold mode for forced network activation, deactivation removes own cron events, uninstall based on an exact manifest (data stays unless deletion was confirmed).
- Course model (R1-04/R1-05/R1-26): courses, modules, lessons, immutable course revisions, authoring meta boxes and REST routes, publish validator with stable VAL codes and acknowledgements. **Schema** (R1-02: 53 tables).
- Learning and exams (R1-06…R1-12, R1-20): nine neutral question types, activity providers, exam rules with blueprints and snapshots, offline buffer and start limit, learning answers with idempotency, course completion, flashcards, favorites, smart trainer v1.
- Access (R1-13/R1-14/R1-43): grant state machine, groups and seats, content gate, protected storage, internal outbox with dead letters.
- Learning area (R1-16), notifications with opt-out and outbound modes (R1-21), basic certificates with verification (R1-44), object cache with generations (R1-25), settings register (R1-31).
- Privacy and operations (R1-17): exporter and eraser, retention jobs, Site Health checks, audit log screen.
- Documentation (R1-24): operator, author, learner, support, privacy, developer and release guides in `docs/user/`.
