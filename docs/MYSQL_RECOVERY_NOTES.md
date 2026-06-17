# MySQL Recovery Notes — June 2026

## Symptom
XAMPP MySQL (MariaDB 10.4.32) refuses to start — turns on and immediately
turns off. The XAMPP control panel shows "Attempting to start MySQL..."
then "MySQL stopped" within a second.

## Root cause
The MySQL data directory at `C:\xampp\mysql\data\` had three compounding
issues from an unclean shutdown (likely a hard power-off or kill during a
write):

1. **Aria log corruption** — `aria_log.00000001` and `aria_log_control`
   in `C:\xampp\mysql\data\` were in an inconsistent state (last log page
   was behind the checkpoint page). Aria backs the mysql.plugin and
   mysql.db system tables, so a corrupt Aria log means the privilege system
   won't initialize.

2. **InnoDB log sequence mismatch** — Page 302 of the system tablespace had
   a future log sequence number (8379511 vs current 7001644). The classic
   "you copied ibdata1 without ib_logfile*" symptom. In this case it was
   a side effect of the Aria crash bringing down MariaDB mid-write.

3. **mysql.db table corruption** — many "Wrong CRC on datapage" errors.
   mysqld attempted safe-repair on startup, refused (too many rows lost),
   and aborted with "Can't open and lock privilege tables".

## Fix (applied 2026-06-14, ~3 minutes)

Step 1 — kill any stuck mysqld:
```bash
taskkill //F //IM mysqld.exe
```

Step 2 — delete the Aria log files (MariaDB recreates them on next start):
```bash
cd C:\xampp\mysql\data
rm -f aria_log.00000001 aria_log_control
```

Step 3 — add temporary `innodb_force_recovery = 1` to my.ini under `[mysqld]`
to bypass the "future LSN" warning. Add `myisam_recover_options = "FORCE,BACKUP"`
to force-repair corrupted MyISAM/Aria tables on startup.

Step 4 — start MySQL via the XAMPP control panel.

Step 5 — once MySQL is up, run aria_chk on any remaining corrupted tables
to fix them outside the mysqld lifecycle:
```bash
C:\xampp\mysql\bin\aria_chk.exe -r -v C:\xampp\mysql\data\mysql\db
```

Step 6 — take a full mysqldump of the highland_fresh schema as soon as
MySQL is back up:
```bash
C:\xampp\mysql\bin\mysqldump.exe -u root highland_fresh > highland_fresh_backup_2026-06-14.sql
```

Step 7 — REMOVE the two temporary my.ini lines (`innodb_force_recovery`
and `myisam_recover_options`) and restart MySQL cleanly. With these lines
present, MySQL is in a degraded mode where it won't write redo log changes,
so leaving them in is a data-loss risk.

## Prevention

  • Always stop MySQL cleanly before shutting down the machine
    (`xampp_stop.exe` or the control panel's Stop button).
  • Do not edit files inside `C:\xampp\mysql\data\` by hand.
  • If you need to copy the data directory, copy both ibdata1 and
    ib_logfile* together, and Aria logs together.
  • Schedule a nightly mysqldump of highland_fresh to a separate drive.
    The current `backup_20260120_230855`, `innodb_backup_20260120_231911`,
    `_highland_fresh_recovered_bak`, and `mysql_backup_corrupt` folders
    in `C:\xampp\mysql\data\` suggest a history of ad-hoc recovery
    attempts; a real backup script would prevent that.

## Files touched

  • `C:\xampp\mysql\data\aria_log.00000001` — deleted
  • `C:\xampp\mysql\data\aria_log_control` — deleted
  • `C:\xampp\mysql\bin\my.ini` — added temporary force_recovery lines
    (REMOVE AFTER RECOVERY IS CONFIRMED STABLE)
