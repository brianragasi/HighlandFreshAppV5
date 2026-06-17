<?php
// Shared key for browser-accessible cron endpoints (e.g. /cron/expire_stale_batches.php).
// In production, generate a long random key and put it in a .env file or your
// secrets manager. This dev key is fine for the localhost review environment.
return 'dev-cron-key-change-me-in-production';
