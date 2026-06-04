<?php
require_once 'config.php';
$db = getDB();

if (!isset($_GET['id'])) { header("Location: index"); exit; }

$orig = $db->prepare("SELECT * FROM invoices WHERE id = ?");
$orig->execute([$_GET['id']]);
$orig = $orig->fetch();
if (!$orig) { header("Location: index"); exit; }

$stmt = $db->prepare("INSERT INTO invoices (invoice_number, date_of_issue, due_date,
    from_name, from_address, from_email, from_vat_no, from_phone, from_account_number, from_swift_bic,
    to_name, to_address, to_email, to_vat_no, to_phone,
    currency, tax_rate, tax_amount, discount_type, discount_value, net_total, gross_total,
    paid_amount, status, notes, payment_method, qr_data, qr_description, qr_show, logo_path, footer_text)
    VALUES (?, CURDATE(), ?,
    ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?,
    0, 'draft', ?, ?, ?, ?, 0, ?, ?)");
$stmt->execute([
    $orig['invoice_number'], $orig['due_date'],
    $orig['from_name'], $orig['from_address'], $orig['from_email'], $orig['from_vat_no'], $orig['from_phone'], $orig['from_account_number'], $orig['from_swift_bic'],
    $orig['to_name'], $orig['to_address'], $orig['to_email'], $orig['to_vat_no'], $orig['to_phone'],
    $orig['currency'], $orig['tax_rate'], $orig['tax_amount'], $orig['discount_type'], $orig['discount_value'], $orig['net_total'], $orig['gross_total'],
    $orig['notes'], $orig['payment_method'], $orig['qr_data'], $orig['qr_description'], $orig['logo_path'], $orig['footer_text']
]);
$newId = $db->lastInsertId();

$items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order");
$items->execute([$orig['id']]);
foreach ($items->fetchAll() as $item) {
    $db->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, amount, sort_order) VALUES (?,?,?,?,?,?)")
        ->execute([$newId, $item['description'], $item['quantity'], $item['unit_price'], $item['amount'], $item['sort_order']]);
}

header("Location: create?id=$newId");
exit;
