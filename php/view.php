<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoice</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <style>
    @media print {
        .no-print,.navbar,.invoice-actions-bar{display:none !important}
        body{background:#fff}
        .container-center{padding:0 !important;max-width:100% !important}
        .invoice-card{box-shadow:none !important;border:none !important;border-radius:0 !important;max-width:100% !important}
    }
    </style>
</head>
<body>
    <nav class="navbar no-print">
        <a href="index.php" class="logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            InvoiceApp
        </a>
        <div class="nav-links">
            <a href="index.php">Invoices</a>
            <a href="create.php" class="btn btn-primary btn-sm">+ New Invoice</a>
        </div>
    </nav>

    <main class="container-center" style="max-width:800px">
        <?php
        require_once 'config.php';
        $db = getDB();
        if (!isset($_GET['id'])) { header("Location: index.php"); exit; }
        $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $invoice = $stmt->fetch();
        if (!$invoice) { echo '<div style="background:#fee2e2;color:#991b1b;padding:1rem;border-radius:8px">Invoice not found. <a href="index.php">Back</a></div>'; exit; }
        $stmt2 = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order");
        $stmt2->execute([$invoice['id']]);
        $items = $stmt2->fetchAll();
        $statusColors = ['draft'=>'#64748b','sent'=>'#2563eb','paid'=>'#16a34a','overdue'=>'#dc2626'];
        $statusBg = ['draft'=>'#f1f5f9','sent'=>'#dbeafe','paid'=>'#dcfce7','overdue'=>'#fee2e2'];
        $sc = $statusColors[$invoice['status']] ?? '#64748b';
        $sb = $statusBg[$invoice['status']] ?? '#f1f5f9';
        $s = $invoice['status'];
        $cur = htmlspecialchars($invoice['currency']);
        $logo = $invoice['logo_path'] ? htmlspecialchars($invoice['logo_path']) : '';
        ?>
        <div class="invoice-actions-bar no-print">
            <a href="create.php?id=<?= $invoice['id'] ?>" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print / PDF
            </button>
            <a href="delete.php?id=<?= $invoice['id'] ?>" class="btn btn-outline" style="color:#dc2626;border-color:#fecaca" onclick="return confirm('Delete this invoice?')">Delete</a>
            <a href="index.php" class="btn btn-ghost" style="margin-left:auto">Back to List</a>
        </div>

        <div class="invoice-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.06)">
            <!-- Header -->
            <div style="padding:40px 40px 32px;border-bottom:3px solid #0f172a">
                <div style="display:flex;justify-content:space-between;align-items:flex-start">
                    <div>
                        <?php if ($logo): ?><img src="<?= $logo ?>" style="max-height:52px;object-fit:contain;margin-bottom:20px"><?php endif; ?>
                        <div style="font-size:42px;font-weight:900;color:#0f172a;letter-spacing:-1.5px;line-height:1">INVOICE</div>
                        <div style="font-size:18px;font-weight:600;color:#475569;margin-top:6px;letter-spacing:0.5px"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                    </div>
                    <div style="text-align:right">
                        <span style="display:inline-block;padding:4px 16px;border-radius:99px;font-size:11px;font-weight:700;background:<?= $sb ?>;color:<?= $sc ?>;text-transform:uppercase;letter-spacing:0.05em"><?= htmlspecialchars($s) ?></span>
                        <div style="margin-top:16px;font-size:13px;color:#64748b"><span style="color:#94a3b8">Issued</span> &nbsp;<strong style="color:#0f172a"><?= htmlspecialchars($invoice['date_of_issue']) ?></strong></div>
                        <?php if ($invoice['due_date']): ?><div style="margin-top:4px;font-size:13px;color:#64748b"><span style="color:#94a3b8">Due</span> &nbsp;<strong style="color:#0f172a"><?= htmlspecialchars($invoice['due_date']) ?></strong></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Parties -->
            <div style="display:flex;gap:0;border-bottom:1px solid #f1f5f9">
                <div style="flex:1;padding:28px 40px">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:0.12em;color:#94a3b8;font-weight:700;margin-bottom:10px">Bill From</div>
                    <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px"><?= htmlspecialchars($invoice['from_name']) ?></div>
                    <div style="font-size:13px;color:#64748b;line-height:1.7">
                        <?php if ($invoice['from_address']): ?><?= htmlspecialchars($invoice['from_address']) ?><br><?php endif; ?>
                        <?php if ($invoice['from_email']): ?><?= htmlspecialchars($invoice['from_email']) ?><br><?php endif; ?>
                        <?php if ($invoice['from_vat_no']): ?>KRA Pin <?= htmlspecialchars($invoice['from_vat_no']) ?><br><?php endif; ?>
                        <?php if ($invoice['from_account_number']): ?>Account: <?= htmlspecialchars($invoice['from_account_number']) ?><br><?php endif; ?>
                        <?php if ($invoice['from_swift_bic']): ?>SWIFT/BIC: <?= htmlspecialchars($invoice['from_swift_bic']) ?><?php endif; ?>
                    </div>
                </div>
                <div style="width:1px;background:#f1f5f9"></div>
                <div style="flex:1;padding:28px 40px">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:0.12em;color:#94a3b8;font-weight:700;margin-bottom:10px">Bill To</div>
                    <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px"><?= htmlspecialchars($invoice['to_name']) ?></div>
                    <div style="font-size:13px;color:#64748b;line-height:1.7">
                        <?php if ($invoice['to_address']): ?><?= htmlspecialchars($invoice['to_address']) ?><br><?php endif; ?>
                        <?php if ($invoice['to_email']): ?><?= htmlspecialchars($invoice['to_email']) ?><?php endif; ?>
                        <?php if ($invoice['to_vat_no']): ?><br>KRA Pin <?= htmlspecialchars($invoice['to_vat_no']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Table -->
            <div style="padding:0 40px">
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <th style="padding:13px 20px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:5%">#</th>
                        <th style="padding:13px 20px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:52%">Description</th>
                        <th style="padding:13px 20px;text-align:center;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:8%">Qty</th>
                        <th style="padding:13px 20px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:17%">Price</th>
                        <th style="padding:13px 20px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:18%">Amount</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($items as $idx => $item): ?>
                            <tr>
                                <td style="padding:13px 20px;font-size:13px;color:#94a3b8;border-bottom:1px solid #f1f5f9"><?= $idx + 1 ?></td>
                                <td style="padding:13px 20px;font-size:13px;color:#0f172a;font-weight:500;border-bottom:1px solid #f1f5f9"><?= htmlspecialchars($item['description']) ?></td>
                                <td style="padding:13px 20px;font-size:13px;text-align:center;color:#475569;border-bottom:1px solid #f1f5f9"><?= number_format($item['quantity'], 2) ?></td>
                                <td style="padding:13px 20px;font-size:13px;text-align:right;color:#475569;border-bottom:1px solid #f1f5f9"><?= number_format($item['unit_price'], 2) ?></td>
                                <td style="padding:13px 20px;font-size:13px;text-align:right;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9"><?= number_format($item['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Totals -->
            <div style="display:flex;justify-content:flex-end;padding:12px 40px 36px">
                <div style="width:300px">
                    <div style="display:flex;justify-content:space-between;padding:10px 20px;font-size:13px;color:#64748b;background:#f8fafc;border-radius:6px 6px 0 0;border-bottom:1px solid #f1f5f9">
                        <span>Subtotal</span><span style="color:#0f172a;font-weight:500"><?= number_format($invoice['net_total'], 2) ?></span>
                    </div>
                    <?php if ($invoice['tax_rate'] > 0): ?>
                        <div style="display:flex;justify-content:space-between;padding:10px 20px;font-size:13px;color:#64748b">
                            <span>Tax (<?= number_format($invoice['tax_rate'], 2) ?>%)</span><span><?= number_format($invoice['tax_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex;justify-content:space-between;padding:14px 20px;background:#0f172a;border-radius:0 0 6px 6px">
                        <div>
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:rgba(255,255,255,0.5);font-weight:600">Total Due</div>
                            <div style="font-size:22px;font-weight:800;color:#fff;margin-top:2px"><?= number_format($invoice['gross_total'], 2) ?> <?= $cur ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($invoice['notes']) || !empty($invoice['payment_method'])): ?>
                <div style="padding:24px 40px;border-top:1px solid #f1f5f9">
                    <div style="display:flex;gap:24px">
                        <?php if (!empty($invoice['notes'])): ?>
                            <div style="flex:1">
                                <div style="font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;margin-bottom:6px">Notes</div>
                                <div style="font-size:13px;color:#475569;white-space:pre-wrap;line-height:1.7"><?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($invoice['payment_method'])): ?>
                            <div style="flex:1">
                                <div style="font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;margin-bottom:6px">Payment Method</div>
                                <div style="font-size:13px;color:#475569;line-height:1.7"><?= htmlspecialchars($invoice['payment_method']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Footer -->
            <div style="padding:18px 40px;background:#0f172a;display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:11px;color:rgba(255,255,255,0.5)">Thank you for your business</span>
                <span style="font-size:11px;color:rgba(255,255,255,0.5)"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
            </div>
        </div>
    </main>
</body>
</html>
