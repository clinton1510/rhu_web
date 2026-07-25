<?php
require_once __DIR__ . '/db.php';

echo "<h1>Admin Account Check</h1>";
echo "<p><strong>PDO Connection:</strong> " . ($pdo ? "✓ Connected" : "✗ Failed (null)") . "</p>";

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 AND (r.name IN ('RHU_ADMIN', 'SUPER_ADMIN', 'ADMIN_STAFF') OR u.role_id = 9)");
        $count = (int) ($stmt ? $stmt->fetchColumn() : 0);
        echo "<p><strong>Active Admin Count:</strong> $count</p>";
        
        if ($count === 0) {
            echo "<p style='color: green;'><strong>✓ Registration should appear!</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>✗ Admin already exists - registration will be blocked</strong></p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>Cannot check - no database connection</strong></p>";
}
?>
<hr>
<p><a href="RHUAdminLogin.php">← Back to Login</a></p>
