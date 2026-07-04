# Use PostgreSQL everywhere

Openlink uses PostgreSQL as the database in production and in local development through Docker. This keeps constraints, indexing, JSON support, and analytics-related behavior consistent between development and production instead of relying on SQLite locally and discovering database differences later.
