<?php
// ─────────────────────────────────────────────────────────────
//  Database configuration  –  adjust credentials as needed
// ─────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'comshop');
define('DB_USER', 'root');
define('DB_PASS', '');          // ← set your MySQL password here

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('
    <div style="font-family:Segoe UI,sans-serif;max-width:560px;margin:80px auto;
                padding:30px;border:1px solid #f5c2c7;border-radius:12px;background:#fff5f5;">
        <h3 style="color:#c0392b;margin:0 0 10px">&#10006; Database Connection Failed</h3>
        <p style="color:#555;margin:0">
            Could not connect to the <strong>comshop</strong> database.<br>
            Make sure MySQL is running and that you have imported <code>setup.sql</code>.
        </p>
    </div>');
}
