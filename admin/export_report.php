<?php
// Simple redirect wrapper for report export. The UI's exportReport() opens this file.
// We redirect to reports.php with a print flag so the reports page can render a
// print-friendly view and trigger the browser print dialog (users can then Save as PDF).

// Preserve query string and set print=1
$qs = $_SERVER['QUERY_STRING'] ?? '';
parse_str($qs, $params);
$params['print'] = '1';

$target = 'reports.php?' . http_build_query($params);
header('Location: ' . $target);
exit;

?>
