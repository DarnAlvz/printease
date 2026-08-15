# Database Migrations

Keep schema changes in this folder as SQL files and run them in filename order.

Recommended filename format:

```text
YYYY_MM_DD_short_description.sql
```

Example:

```text
2026_06_16_add_payment_ocr_fields.sql
```

## Fresh install (new / empty database)

1. Import `0000_base_schema.sql` only. It is a schema-only export of the
   current database (no data) and already contains every prior migration
   (rate limiting, concurrency guards, unified pricing, payment channels,
   and the `orders.submit_token` guard), so it is safe to commit to the
   repository.
2. Do not run older migration files on top of it — they are consolidated
   into the base schema. Only apply a new migration dated after the base
   schema was exported.

`pe_database.sql` is a local phpMyAdmin backup that includes real user data. It must
stay out of version control (see `.gitignore`) and is only a personal restore
backup, not the reproducibility source. Regenerate `0000_base_schema.sql` from it
(schema-only) when your local database changes and you want to publish the new state.

## Existing databases

Before running a migration, back up the local database. After running it, note the filename and date in your capstone documentation so the database state is reproducible.

For existing local databases, run every new migration file after pulling code changes. For example, the latest is `2026_08_14_add_order_submit_token.sql` (one-time order submission token); databases imported from the current `0000_base_schema.sql` already have it. Note: this repository tracks the consolidated base schema only; individual migration files have been folded into it.
