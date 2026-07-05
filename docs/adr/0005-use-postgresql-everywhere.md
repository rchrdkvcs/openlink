# Use PostgreSQL everywhere

Openlink uses PostgreSQL as the database in production and local development. This keeps constraints, indexing, JSON support, and analytics-related behavior consistent between environments instead of relying on SQLite locally and discovering database differences later.
