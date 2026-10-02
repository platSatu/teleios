<?php

/*
 * Backup Database (App\Services\Backup\DatabaseBackupService).
 * File .sql.gz disimpan di disk "local" (storage/app/private), tidak bisa
 * dibuka lewat URL publik -- hanya lewat halaman Superadmin > Backup Database.
 */
return [
    // Path mysqldump. Di VPS biasanya cukup "mysqldump"; di XAMPP Windows mis.
    // C:\xampp\mysql\bin\mysqldump.exe (isi MYSQLDUMP_BINARY di .env).
    'mysqldump' => env('MYSQLDUMP_BINARY', 'mysqldump'),

    // Folder di dalam disk local.
    'directory' => 'backups',

    // Backup lebih tua dari ini dihapus otomatis (backup sukses terakhir selalu disimpan).
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
];
