<?php
require __DIR__ . '/../monitoring-mobil-kantor/config/database.php';
$pdo = db();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $c = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo "$t: $c rows\n";
}
