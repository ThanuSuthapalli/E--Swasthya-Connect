<?php
require_once '../includes/config.php';
requireRole('doctor');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo "Invalid response id";
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT mr.*, p.title as problem_title, u.name as villager_name FROM medical_responses mr JOIN problems p ON mr.problem_id = p.id JOIN users u ON p.villager_id = u.id WHERE mr.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $resp = $stmt->fetch();
} catch (Exception $e) {
    error_log('Print response error: ' . $e->getMessage());
    $resp = null;
}

if (!$resp) {
    echo "Response not found.";
    exit;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Response #<?php echo htmlspecialchars($resp['id']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .card { border: 1px solid #ccc; padding: 15px; border-radius: 6px; }
        .meta { font-size: 0.9rem; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Medical Response Report</h2>
        <p class="meta">Response ID: <?php echo htmlspecialchars($resp['id']); ?> | Case: <?php echo htmlspecialchars($resp['problem_id']); ?> - <?php echo htmlspecialchars($resp['problem_title']); ?></p>
    </div>

    <div class="card">
        <h3>Medical Response</h3>
        <p><?php echo nl2br(htmlspecialchars($resp['response'])); ?></p>

        <?php if (!empty($resp['recommendations'])): ?>
            <h4>Recommendations</h4>
            <p><?php echo nl2br(htmlspecialchars($resp['recommendations'])); ?></p>
        <?php endif; ?>

        <hr>
        <p class="meta">Doctor ID: <?php echo htmlspecialchars($resp['doctor_id']); ?> | Submitted: <?php echo htmlspecialchars($resp['created_at']); ?></p>
    </div>

    <script>
        window.onload = function() { window.print(); };
    </script>
</body>
</html>
