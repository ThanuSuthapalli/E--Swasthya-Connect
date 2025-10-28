<?php
// Backwards-compatible wrapper. Some pages (dashboard, buttons) link to system_reports.php
// while the canonical reports implementation lives in reports.php. Include it here so both
// paths work without changing multiple files.

require_once __DIR__ . '/../includes/config.php';
requireRole('admin');

// Include the main reports page. Use an absolute include to avoid path issues.
include __DIR__ . '/reports.php';

?>
