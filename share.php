<?php
require_once 'config.php';
$db = getDB();
header('Content-Type: application/json');

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'generate';

if (!$id) { echo json_encode(['error' => 'Missing id']); exit; }

$stmt = $db->prepare("SELECT id, invoice_number, share_token FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) { echo json_encode(['error' => 'Invoice not found']); exit; }

if ($action === 'revoke') {
    $db->prepare("UPDATE invoices SET share_token = NULL WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true, 'token' => null]);
    exit;
}

if ($invoice['share_token']) {
    $token = $invoice['share_token'];
} else {
    $token = bin2hex(random_bytes(16));
    $db->prepare("UPDATE invoices SET share_token = ? WHERE id = ?")->execute([$token, $id]);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base = dirname($_SERVER['SCRIPT_NAME']);
$url = $scheme . '://' . $host . $base . '/view-public?token=' . $token;

echo json_encode([
    'ok' => true,
    'token' => $token,
    'url' => $url,
    'invoice_number' => $invoice['invoice_number']
]);
