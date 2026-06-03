<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$db = getDB();
$stmt = $db->prepare("DELETE FROM invoices WHERE id = ?");
$stmt->execute([$_GET['id']]);

header("Location: index.php");
exit;
