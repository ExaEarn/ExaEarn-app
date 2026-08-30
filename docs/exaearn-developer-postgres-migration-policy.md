# Developer Platform PostgreSQL Migration Policy

Production migrations use expand/contract sequencing. Additive schema is deployed before application code consumes it; backfills are bounded, observable, and restartable; constraints and removals occur only after old readers and writers are retired.

`scripts/classify-migrations.php` emits `SAFE_AUTOMATED`, `REVIEW_REQUIRED`, `DESTRUCTIVE`, `DATA_MIGRATION`, and `POSTGRES_REHEARSAL_REQUIRED` records. Raw SQL and unknown patterns are never called safe. CI executes the complete chain on PostgreSQL 16. Reviewed operations require lock, table-size, index, compatibility, and rollback analysis.

Rollback is not assumed to reverse data loss. Destructive and data migrations require backup/PITR confirmation and a forward-repair plan. Potentially blocking indexes require a separately approved online/concurrent strategy where PostgreSQL transaction rules permit it.
