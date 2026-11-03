# SkyTruFiber Customer Portal

Standalone customer-facing PHP application for https://ahba.skytrufiber.com.

PHP 8.2 + PostgreSQL PDO. Deploy this repository to Render from main. Configure PGHOST, PGDATABASE, PGUSER, PGPASSWORD, SMTP_USERNAME, and SMTP_PASSWORD in Render environment settings. Optional SMTP_HOST, SMTP_PORT, and CRON_SECRET. Never commit credentials. The app uses the existing Neon schema; do not run destructive schema changes. Original files in skytrufiber-site remain unchanged.
