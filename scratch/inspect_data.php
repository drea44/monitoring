<?php
require __DIR__ . '/../monitoring-mobil-kantor/config/database.php';
$pdo = db();

echo "--- USERS ---\n";
print_r($pdo->query("SELECT id, name, email, role FROM users")->fetchAll());

echo "--- CARS ---\n";
print_r($pdo->query("SELECT id, name, plate_number, status FROM cars")->fetchAll());

echo "--- DRIVERS ---\n";
print_r($pdo->query("SELECT id, name, status FROM drivers")->fetchAll());
