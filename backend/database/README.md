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

Before running a migration, back up the local database. After running it, note the filename and date in your capstone documentation so the database state is reproducible.
