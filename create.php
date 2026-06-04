<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<div style='background:#fee;border:2px solid red;padding:20px;margin:20px;font-family:monospace;'><b>PHP ERROR:</b> $errstr<br><b>File:</b> $errfile<br><b>Line:</b> $errline</div>";
    return false;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($_GET['id']) ? 'Edit' : 'New' ?> Invoice</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
    <nav class="navbar no-print">
        <a href="./" class="logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            InvoiceApp
        </a>
        <div class="nav-links">
            <a href="./">Invoices</a>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('invoiceForm').requestSubmit()">Save Invoice</button>
        </div>
    </nav>

    <?php
    require_once 'config.php';
    $db = getDB();
    $editMode = false;
    $invoice = null;
    $items = [];

    if (isset($_GET['id'])) {
        $editMode = true;
        $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $invoice = $stmt->fetch();
        if ($invoice) {
            $stmt2 = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order");
            $stmt2->execute([$invoice['id']]);
            $items = $stmt2->fetchAll();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = $_POST;
        $invoiceId = $data['invoice_id'] ?? null;
        $netTotal = 0;
        $descriptions = $data['item_description'] ?? [];
        $quantities = $data['item_quantity'] ?? [];
        $prices = $data['item_price'] ?? [];
        for ($i = 0; $i < count($descriptions); $i++) {
            if (!empty($descriptions[$i])) $netTotal += (float)$quantities[$i] * (float)$prices[$i];
        }
        $taxRate = (float)($data['tax_rate'] ?? 0);
        $taxAmount = $netTotal * ($taxRate / 100);
        $discountType = $data['discount_type'] ?? 'percent';
        $discountValue = (float)($data['discount_value'] ?? 0);
        if ($discountType === 'percent') {
            $discountAmount = $netTotal * ($discountValue / 100);
        } else {
            $discountAmount = $discountValue;
        }
        $grossTotal = $netTotal - $discountAmount + $taxAmount;
        $paidAmount = (float)($data['paid_amount'] ?? 0);

        $logoPath = null;
        $removeLogo = !empty($data['remove_logo']) && $data['remove_logo'] === '1';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                $logoName = 'logo_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/uploads/' . $logoName);
                $logoPath = 'uploads/' . $logoName;
            }
        }

        if ($invoiceId && $logoPath === null && !$removeLogo && !empty($data['existing_logo'])) {
            $logoPath = $data['existing_logo'];
        }

        if ($removeLogo && $logoPath === null) {
            $logoPath = '';
        }

        $notes = $data['notes'] ?? '';
        $paymentMethod = $data['payment_method'] ?? '';
        $qrData = $data['qr_data'] ?? '';
        $qrDescription = $data['qr_description'] ?? '';
        $qrShow = isset($data['qr_show']) ? 1 : 0;
        $footerText = $data['footer_text'] ?? 'Thank you for your business';
        $discountType = $data['discount_type'] ?? 'percent';
        $discountValue = (float)($data['discount_value'] ?? 0);

        if ($invoiceId) {
            $sql = "UPDATE invoices SET invoice_number=?, date_of_issue=?, due_date=?,
                from_name=?, from_address=?, from_email=?, from_vat_no=?, from_phone=?, from_account_number=?, from_swift_bic=?,
                to_name=?, to_address=?, to_email=?, to_vat_no=?, to_phone=?,
                currency=?, tax_rate=?, tax_amount=?, discount_type=?, discount_value=?, net_total=?, gross_total=?,
                paid_amount=?, status=?, notes=?, payment_method=?, qr_data=?, qr_description=?, qr_show=?, logo_path=?, footer_text=? WHERE id=?";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['invoice_number'], $data['date_of_issue'], $data['due_date'] ?: null,
                $data['from_name'], $data['from_address'], $data['from_email'], $data['from_vat_no'], $data['from_phone'] ?? '', $data['from_account_number'], $data['from_swift_bic'],
                $data['to_name'], $data['to_address'], $data['to_email'], $data['to_vat_no'], $data['to_phone'] ?? '',
                $data['currency'], $taxRate, $taxAmount, $discountType, $discountValue, $netTotal, $grossTotal,
                $paidAmount, $data['status'], $notes, $paymentMethod, $qrData, $qrDescription, $qrShow, $logoPath, $footerText, $invoiceId
            ]);
            $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoiceId]);
        } else {
            $sql = "INSERT INTO invoices SET invoice_number=?, date_of_issue=?, due_date=?,
                from_name=?, from_address=?, from_email=?, from_vat_no=?, from_phone=?, from_account_number=?, from_swift_bic=?,
                to_name=?, to_address=?, to_email=?, to_vat_no=?, to_phone=?,
                currency=?, tax_rate=?, tax_amount=?, discount_type=?, discount_value=?, net_total=?, gross_total=?,
                paid_amount=?, status=?, notes=?, payment_method=?, qr_data=?, qr_description=?, qr_show=?, logo_path=?, footer_text=?";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['invoice_number'], $data['date_of_issue'], $data['due_date'] ?: null,
                $data['from_name'], $data['from_address'], $data['from_email'], $data['from_vat_no'], $data['from_phone'] ?? '', $data['from_account_number'], $data['from_swift_bic'],
                $data['to_name'], $data['to_address'], $data['to_email'], $data['to_vat_no'], $data['to_phone'] ?? '',
                $data['currency'], $taxRate, $taxAmount, $discountType, $discountValue, $netTotal, $grossTotal,
                $paidAmount, $data['status'], $notes, $paymentMethod, $qrData, $qrDescription, $qrShow, $logoPath, $footerText
            ]);
            $invoiceId = $db->lastInsertId();
        }
        for ($i = 0; $i < count($descriptions); $i++) {
            if (!empty($descriptions[$i])) {
                $amt = (float)$quantities[$i] * (float)$prices[$i];
                $db->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, amount, sort_order) VALUES (?,?,?,?,?,?)")
                    ->execute([$invoiceId, $descriptions[$i], $quantities[$i], $prices[$i], $amt, $i]);
            }
        }
        header("Location: view?id=$invoiceId");
        exit;
    }

    $val = fn($field, $default='') => $editMode && $invoice ? htmlspecialchars($invoice[$field] ?? $default) : htmlspecialchars($default);
    ?>

    <div class="editor-layout">
        <!-- Left: Form Panel -->
        <div class="editor-form">
            <form method="POST" enctype="multipart/form-data" id="invoiceForm">
                <?php if ($editMode): ?>
                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                    <input type="hidden" name="existing_logo" value="<?= $val('logo_path') ?>">
                <?php endif; ?>

                <!-- Invoice Details -->
                <div class="form-section">
                    <div class="form-section-title">Invoice Details</div>
                    <div class="form-section-body">
                        <div class="form-group">
                            <label>Invoice Number</label>
                            <input type="text" name="invoice_number" id="invoiceNumber" required
                                value="<?= $val('invoice_number', 'INV-' . rand(100,999)) ?>"
                                oninput="updatePreview()">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date of Issue</label>
                                <input type="date" name="date_of_issue" id="dateOfIssue" required
                                    value="<?= $val('date_of_issue', date('Y-m-d')) ?>"
                                    oninput="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date" id="dueDate"
                                    value="<?= $val('due_date', date('Y-m-d', strtotime('+30 days'))) ?>"
                                    oninput="updatePreview()">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="status" onchange="updatePreview()">
                                    <?php
                                    $statuses = ['draft','sent','paid','overdue'];
                                    $cur = $editMode ? $invoice['status'] : 'draft';
                                    foreach ($statuses as $s):
                                    ?>
                                        <option value="<?=$s?>" <?= $s === $cur ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Currency</label>
                                <div class="currency-picker" id="currencyPicker">
                                    <div class="currency-trigger" onclick="toggleCurrencyDropdown()">
                                        <span class="flag" id="currencyFlag">$</span>
                                        <span class="code" id="currencyCode"><?= $val('currency', 'USD') ?></span>
                                        <svg class="arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                    </div>
                                    <input type="hidden" name="currency" id="currencyInput" value="<?= $val('currency', 'USD') ?>">
                                    <div class="currency-dropdown" id="currencyDropdown">
                                        <div class="currency-search"><input type="text" placeholder="Search currencies..." id="currencySearch" oninput="filterCurrencies()"></div>
                                        <div class="currency-list" id="currencyList"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Logo</label>
                            <div class="logo-upload <?= $editMode && $invoice['logo_path'] ? 'has-logo' : '' ?>" id="logoUpload" onclick="document.getElementById('logoInput').click()">
                                <input type="file" name="logo" id="logoInput" accept="image/*" onchange="previewLogo(this)">
                                <?php if ($editMode && $invoice['logo_path']): ?>
                                    <img src="<?= htmlspecialchars($invoice['logo_path']) ?>" id="logoPreview">
                                    <button type="button" class="logo-remove" id="logoRemove" onclick="event.stopPropagation();removeLogo()" title="Remove logo">&times;</button>
                                <?php else: ?>
                                    <div class="placeholder" id="logoPlaceholder">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        Click to upload logo
                                    </div>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="remove_logo" id="removeLogoField" value="0">
                            <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
                                <label style="font-size:11px;color:var(--text-muted);white-space:nowrap">Logo size</label>
                                <input type="range" id="logoHeight" min="16" max="120" value="48" style="flex:1;accent-color:var(--primary)" oninput="updateLogoSize()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- From (Your Details) -->
                <div class="form-section">
                    <div class="form-section-title">From (Your Details)</div>
                    <div class="form-section-body">
                        <div class="form-group">
                            <label>Business Name</label>
                            <input type="text" name="from_name" id="fromName" required
                                value="<?= $val('from_name') ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="from_address" id="fromAddress"
                                value="<?= $val('from_address') ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="from_email" id="fromEmail"
                                value="<?= $val('from_email') ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tax ID</label>
                                <input type="text" name="from_vat_no" id="fromVat"
                                    value="<?= $val('from_vat_no') ?>" oninput="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="from_phone" id="fromPhone"
                                    value="<?= $val('from_phone') ?>" oninput="updatePreview()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- To (Client Details) -->
                <div class="form-section">
                    <div class="form-section-title">To (Client Details)</div>
                    <div class="form-section-body">
                        <div class="form-group">
                            <label>Client Name</label>
                            <input type="text" name="to_name" id="toName" required
                                value="<?= $val('to_name') ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="to_address" id="toAddress"
                                value="<?= $val('to_address') ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="to_email" id="toEmail"
                                value="<?= $val('to_email') ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tax ID</label>
                                <input type="text" name="to_vat_no" id="toVat"
                                    value="<?= $val('to_vat_no') ?>" oninput="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="to_phone" id="toPhone"
                                    value="<?= $val('to_phone') ?>" oninput="updatePreview()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="form-section">
                    <div class="form-section-title">Items</div>
                    <div class="form-section-body">
                        <table class="items-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th class="col-desc">Description</th>
                                    <th class="col-qty">Qty</th>
                                    <th class="col-price">Unit Price</th>
                                    <th class="col-amount">Amount</th>
                                    <th class="col-action"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php if ($editMode && !empty($items)): ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr class="item-row">
                                            <td><input type="text" name="item_description[]" required value="<?= htmlspecialchars($item['description']) ?>" oninput="updatePreview()"></td>
                                            <td><input type="number" name="item_quantity[]" step="0.01" min="0" required value="<?= $item['quantity'] ?>" oninput="calcRow(this);updatePreview()"></td>
                                            <td><input type="number" name="item_price[]" step="0.01" min="0" required value="<?= $item['unit_price'] ?>" oninput="calcRow(this);updatePreview()"></td>
                                            <td class="row-amount"><?= number_format($item['amount'], 2) ?></td>
                                            <td><button type="button" class="btn btn-sm btn-ghost" onclick="removeRow(this);updatePreview()" title="Remove"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="item-row">
                                        <td><input type="text" name="item_description[]" required oninput="updatePreview()"></td>
                                        <td><input type="number" name="item_quantity[]" step="0.01" min="0" required value="1" oninput="calcRow(this);updatePreview()"></td>
                                        <td><input type="number" name="item_price[]" step="0.01" min="0" required value="0" oninput="calcRow(this);updatePreview()"></td>
                                        <td class="row-amount">0.00</td>
                                        <td><button type="button" class="btn btn-sm btn-ghost" onclick="removeRow(this);updatePreview()" title="Remove"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-outline btn-sm add-item-btn" onclick="addRow();updatePreview()">+ Add Item</button>
                    </div>
                </div>

                <!-- Payment Options -->
                <div class="form-section">
                    <div class="form-section-title">Payment Options</div>
                    <div class="form-section-body">
                        <div class="form-group">
                            <label>Payment Method</label>
                            <input type="text" name="payment_method" id="paymentMethod" placeholder="e.g. M-Pesa, Bank Transfer, PayPal"
                                value="<?= $val('payment_method') ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Account Number</label>
                                <input type="text" name="from_account_number" id="fromAccount"
                                    value="<?= $val('from_account_number') ?>" oninput="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label>SWIFT / BIC</label>
                                <input type="text" name="from_swift_bic" id="fromSwift"
                                    value="<?= $val('from_swift_bic') ?>" oninput="updatePreview()">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Amount Paid</label>
                            <input type="number" name="paid_amount" id="paidAmount" step="0.01" min="0"
                                value="<?= $val('paid_amount', '0') ?>" oninput="calcTotals();updatePreview()">
                        </div>
                    </div>
                </div>

                <!-- Notes & Tax -->
                <div class="form-section">
                    <div class="form-section-title">Notes & Tax</div>
                    <div class="form-section-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Notes (visible on invoice)</label>
                                <textarea name="notes" id="notes" rows="6" oninput="updatePreview()"><?= $val('notes') ?></textarea>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:.75rem">
                                <div class="form-group" style="margin-bottom:0">
                                    <label>Tax Rate (%)</label>
                                    <input type="number" name="tax_rate" id="taxRate" step="0.01" min="0" max="100"
                                        value="<?= $val('tax_rate', '0') ?>" oninput="calcTotals();updatePreview()">
                                </div>
                                <div class="form-group" style="margin-bottom:0">
                                    <label>Discount</label>
                                    <div class="input-group">
                                        <input type="number" name="discount_value" id="discountValue" step="0.01" min="0"
                                            value="<?= $val('discount_value', '0') ?>" oninput="calcTotals();updatePreview()" style="flex:1">
                                        <select name="discount_type" id="discountType" onchange="calcTotals();updatePreview()" style="width:auto;min-width:80px">
                                            <option value="percent" <?= ($val('discount_type') ?: 'percent') === 'percent' ? 'selected' : '' ?>>%</option>
                                            <option value="amount" <?= ($val('discount_type') === 'amount') ? 'selected' : '' ?>>Amount</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="totals-card">
                            <div class="total-line"><span>Net Total</span><strong id="netTotal">0.00</strong></div>
                            <div class="total-line" id="discountLine" style="display:none"><span>Discount</span><span id="discountAmount" style="color:var(--danger)">-0.00</span></div>
                            <div class="total-line"><span>Tax</span><span id="taxAmount">0.00</span></div>
                            <div class="total-line total-gross"><span>Gross Total</span><strong id="grossTotal">0.00</strong></div>
                        </div>
                    </div>
                </div>

                <!-- QR Code -->
                <div class="form-section">
                    <div class="form-section-title" style="display:flex;align-items:center;justify-content:space-between">
                        <span>QR Code</span>
                        <label style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;font-weight:500;color:var(--text-secondary);text-transform:none;letter-spacing:0;margin:0;cursor:pointer">
                            <span>Show in PDF</span>
                            <input type="checkbox" name="qr_show" id="qrShow" value="1" onchange="updatePreview()" style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer" <?= $val('qr_show') ? 'checked' : '' ?>>
                        </label>
                    </div>
                    <div class="form-section-body">
                        <div class="form-group">
                            <label>Data</label>
                            <input type="text" name="qr_data" id="qrData" placeholder="Enter URL or text to encode"
                                value="<?= $val('qr_data') ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Description (optional)</label>
                            <input type="text" name="qr_description" id="qrDescription" placeholder="Enter a description for the QR code"
                                value="<?= $val('qr_description') ?>" oninput="updatePreview()">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Footer</div>
                    <div class="form-section-body">
                        <div class="form-group">
                            <label>Footer Text</label>
                            <input type="text" name="footer_text" id="footerText" placeholder="Thank you for your business"
                                value="<?= $val('footer_text', 'Thank you for your business') ?>" oninput="updatePreview()">
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <a href="./" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg">Save Invoice</button>
                </div>
            </form>
        </div>

        <!-- Right: Live Preview Panel -->
        <div class="editor-preview">
            <div class="preview-container">
                <div class="preview-toolbar">
                    <span class="preview-toolbar-title">Live Preview</span>
                    <div style="display:flex;align-items:center;gap:.75rem">
                        <div class="template-tabs">
                            <button class="template-tab active" onclick="switchTemplate('default',this)">Classic</button>
                            <button class="template-tab" onclick="switchTemplate('modern',this)">Modern</button>
                        </div>
                        <div class="preview-actions">
                            <button class="btn btn-sm btn-primary" onclick="printPreview()" title="Download PDF">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Download PDF
                            </button>
                        </div>
                    </div>
                </div>
                <div class="preview-scroll">
                    <div id="previewContainer" style="width:100%;background:#fff;overflow:hidden"></div>
                </div>
            </div>
        </div>
    </div>

<script>
const CURRENCIES = [
    {code:'USD',symbol:'$',name:'US Dollar',flag:'🇺🇸'},
    {code:'EUR',symbol:'€',name:'Euro',flag:'🇪🇺'},
    {code:'GBP',symbol:'£',name:'British Pound',flag:'🇬🇧'},
    {code:'CAD',symbol:'C$',name:'Canadian Dollar',flag:'🇨🇦'},
    {code:'AUD',symbol:'A$',name:'Australian Dollar',flag:'🇦🇺'},
    {code:'JPY',symbol:'¥',name:'Japanese Yen',flag:'🇯🇵'},
    {code:'CHF',symbol:'CHF',name:'Swiss Franc',flag:'🇨🇭'},
    {code:'CNY',symbol:'¥',name:'Chinese Yuan',flag:'🇨🇳'},
    {code:'INR',symbol:'₹',name:'Indian Rupee',flag:'🇮🇳'},
    {code:'MXN',symbol:'$',name:'Mexican Peso',flag:'🇲🇽'},
    {code:'BRL',symbol:'R$',name:'Brazilian Real',flag:'🇧🇷'},
    {code:'KRW',symbol:'₩',name:'South Korean Won',flag:'🇰🇷'},
    {code:'SGD',symbol:'S$',name:'Singapore Dollar',flag:'🇸🇬'},
    {code:'HKD',symbol:'HK$',name:'Hong Kong Dollar',flag:'🇭🇰'},
    {code:'NOK',symbol:'kr',name:'Norwegian Krone',flag:'🇳🇴'},
    {code:'SEK',symbol:'kr',name:'Swedish Krona',flag:'🇸🇪'},
    {code:'DKK',symbol:'kr',name:'Danish Krone',flag:'🇩🇰'},
    {code:'PLN',symbol:'zł',name:'Polish Zloty',flag:'🇵🇱'},
    {code:'CZK',symbol:'Kč',name:'Czech Koruna',flag:'🇨🇿'},
    {code:'HUF',symbol:'Ft',name:'Hungarian Forint',flag:'🇭🇺'},
    {code:'RON',symbol:'lei',name:'Romanian Leu',flag:'🇷🇴'},
    {code:'TRY',symbol:'₺',name:'Turkish Lira',flag:'🇹🇷'},
    {code:'RUB',symbol:'₽',name:'Russian Ruble',flag:'🇷🇺'},
    {code:'ZAR',symbol:'R',name:'South African Rand',flag:'🇿🇦'},
    {code:'AED',symbol:'د.إ',name:'UAE Dirham',flag:'🇦🇪'},
    {code:'SAR',symbol:'﷼',name:'Saudi Riyal',flag:'🇸🇦'},
    {code:'ILS',symbol:'₪',name:'Israeli Shekel',flag:'🇮🇱'},
    {code:'EGP',symbol:'E£',name:'Egyptian Pound',flag:'🇪🇬'},
    {code:'NGN',symbol:'₦',name:'Nigerian Naira',flag:'🇳🇬'},
    {code:'THB',symbol:'฿',name:'Thai Baht',flag:'🇹🇭'},
    {code:'IDR',symbol:'Rp',name:'Indonesian Rupiah',flag:'🇮🇩'},
    {code:'MYR',symbol:'RM',name:'Malaysian Ringgit',flag:'🇲🇾'},
    {code:'PHP',symbol:'₱',name:'Philippine Peso',flag:'🇵🇭'},
    {code:'VND',symbol:'₫',name:'Vietnamese Dong',flag:'🇻🇳'},
    {code:'NZD',symbol:'NZ$',name:'New Zealand Dollar',flag:'🇳🇿'},
    {code:'TWD',symbol:'NT$',name:'Taiwan Dollar',flag:'🇹🇼'},
    {code:'PKR',symbol:'Rs',name:'Pakistani Rupee',flag:'🇵🇰'},
    {code:'BDT',symbol:'৳',name:'Bangladeshi Taka',flag:'🇧🇩'},
    {code:'LKR',symbol:'Rs',name:'Sri Lankan Rupee',flag:'🇱🇰'},
    {code:'GHS',symbol:'GH₵',name:'Ghanaian Cedi',flag:'🇬🇭'},
    {code:'KES',symbol:'KSh',name:'Kenyan Shilling',flag:'🇰🇪'},
    {code:'MAD',symbol:'MAD',name:'Moroccan Dirham',flag:'🇲🇦'},
    {code:'COP',symbol:'$',name:'Colombian Peso',flag:'🇨🇴'},
    {code:'ARS',symbol:'$',name:'Argentine Peso',flag:'🇦🇷'},
    {code:'CLP',symbol:'$',name:'Chilean Peso',flag:'🇨🇱'},
    {code:'PEN',symbol:'S/',name:'Peruvian Sol',flag:'🇵🇪'},
    {code:'UAH',symbol:'₴',name:'Ukrainian Hryvnia',flag:'🇺🇦'},
    {code:'BGN',symbol:'лв',name:'Bulgarian Lev',flag:'🇧🇬'},
    {code:'HRK',symbol:'kn',name:'Croatian Kuna',flag:'🇭🇷'},
    {code:'ISK',symbol:'kr',name:'Icelandic Krona',flag:'🇮🇸'},
    {code:'JMD',symbol:'J$',name:'Jamaican Dollar',flag:'🇯🇲'},
    {code:'CRC',symbol:'₡',name:'Costa Rican Colón',flag:'🇨🇷'},
    {code:'GTQ',symbol:'Q',name:'Guatemalan Quetzal',flag:'🇬🇹'},
    {code:'HNL',symbol:'L',name:'Honduran Lempira',flag:'🇭🇳'},
    {code:'NIO',symbol:'C$',name:'Nicaraguan Córdoba',flag:'🇳🇮'},
    {code:'PAB',symbol:'B/.',name:'Panamanian Balboa',flag:'🇵🇦'},
    {code:'UYU',symbol:'$',name:'Uruguayan Peso',flag:'🇺🇾'},
    {code:'PYG',symbol:'₲',name:'Paraguayan Guarani',flag:'🇵🇾'},
    {code:'BOB',symbol:'Bs',name:'Bolivian Boliviano',flag:'🇧🇴'},
    {code:'VES',symbol:'Bs.S',name:'Venezuelan Bolívar',flag:'🇻🇪'},
    {code:'DZD',symbol:'د.ج',name:'Algerian Dinar',flag:'🇩🇿'},
    {code:'TND',symbol:'د.ت',name:'Tunisian Dinar',flag:'🇹🇳'},
    {code:'JOD',symbol:'JD',name:'Jordanian Dinar',flag:'🇯🇴'},
    {code:'BHD',symbol:'BD',name:'Bahraini Dinar',flag:'🇧🇭'},
    {code:'KWD',symbol:'KD',name:'Kuwaiti Dinar',flag:'🇰🇼'},
    {code:'QAR',symbol:'﷼',name:'Qatari Riyal',flag:'🇶🇦'},
    {code:'OMR',symbol:'﷼',name:'Omani Rial',flag:'🇴🇲'},
    {code:'IRR',symbol:'﷼',name:'Iranian Rial',flag:'🇮🇷'},
    {code:'IQD',symbol:'ع.د',name:'Iraqi Dinar',flag:'🇮🇶'},
    {code:'LBP',symbol:'L£',name:'Lebanese Pound',flag:'🇱🇧'},
    {code:'GEL',symbol:'₾',name:'Georgian Lari',flag:'🇬🇪'},
    {code:'AMD',symbol:'֏',name:'Armenian Dram',flag:'🇦🇲'},
    {code:'AZN',symbol:'₼',name:'Azerbaijani Manat',flag:'🇦🇿'},
    {code:'KZT',symbol:'₸',name:'Kazakhstani Tenge',flag:'🇰🇿'},
    {code:'UZS',symbol:'сўм',name:'Uzbekistani Som',flag:'🇺🇿'},
    {code:'MNT',symbol:'₮',name:'Mongolian Tugrik',flag:'🇲🇳'},
    {code:'LAK',symbol:'₭',name:'Lao Kip',flag:'🇱🇦'},
    {code:'KHR',symbol:'៛',name:'Cambodian Riel',flag:'🇰🇭'},
    {code:'MMK',symbol:'K',name:'Myanmar Kyat',flag:'🇲🇲'},
    {code:'NPR',symbol:'Rs',name:'Nepalese Rupee',flag:'🇳🇵'},
    {code:'MVR',symbol:'Rf',name:'Maldivian Rufiyaa',flag:'🇲🇻'},
    {code:'BND',symbol:'B$',name:'Brunei Dollar',flag:'🇧🇳'},
    {code:'FJD',symbol:'FJ$',name:'Fijian Dollar',flag:'🇫🇯'},
    {code:'PGK',symbol:'K',name:'Papua New Guinean Kina',flag:'🇵🇬'},
    {code:'TOP',symbol:'T$',name:'Tongan Paʻanga',flag:'🇹🇴'},
    {code:'WST',symbol:'WS$',name:'Samoan Tala',flag:'🇼🇸'},
    {code:'VUV',symbol:'VT',name:'Vanuatu Vatu',flag:'🇻🇺'},
];

let currentCurrency = '<?= $val("currency", "USD") ?>';
let currentTemplate = 'default';

function getCurrency(code) {
    return CURRENCIES.find(c => c.code === code) || {code:'USD',symbol:'$',name:'US Dollar',flag:'🇺🇸'};
}

function initCurrencyPicker() {
    const list = document.getElementById('currencyList');
    list.innerHTML = CURRENCIES.map(c =>
        `<div class="currency-option ${c.code === currentCurrency ? 'selected' : ''}" onclick="selectCurrency('${c.code}')">
            <span class="flag">${c.flag}</span>
            <span><strong>${c.code}</strong></span>
            <span class="name">${c.name} (${c.symbol})</span>
        </div>`
    ).join('');
    updateCurrencyDisplay();
}

function updateCurrencyDisplay() {
    const c = getCurrency(currentCurrency);
    document.getElementById('currencyFlag').textContent = c.flag;
    document.getElementById('currencyCode').textContent = c.code;
    document.getElementById('currencyInput').value = c.code;
}

function toggleCurrencyDropdown() {
    document.getElementById('currencyDropdown').classList.toggle('open');
}

function selectCurrency(code) {
    currentCurrency = code;
    updateCurrencyDisplay();
    document.querySelectorAll('.currency-option').forEach(el => el.classList.remove('selected'));
    event.target.closest('.currency-option').classList.add('selected');
    document.getElementById('currencyDropdown').classList.remove('open');
    updatePreview();
}

function filterCurrencies() {
    const q = document.getElementById('currencySearch').value.toLowerCase();
    document.querySelectorAll('.currency-option').forEach(el => {
        const text = el.textContent.toLowerCase();
        el.style.display = text.includes(q) ? '' : 'none';
    });
}

document.addEventListener('click', e => {
    if (!e.target.closest('.currency-picker')) {
        document.getElementById('currencyDropdown').classList.remove('open');
    }
});

function printPreview() {
    const invNum = getVal('invoiceNumber', 'INV');
    const content = document.getElementById('previewContainer').innerHTML;
    const win = window.open('','','width=900,height=700');
    win.document.write(`<!DOCTYPE html><html><head><title>${invNum}</title>
        <style>
          @page{margin:0;size:A4 portrait}
          html{margin:0;padding:0}
          body{margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact;width:210mm;min-height:297mm}
          *{margin:0;padding:0;box-sizing:border-box}
        </style></head><body>${content}</body></html>`);
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); win.close(); }, 300);
}

function addRow() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'item-row fade-in';
    row.innerHTML = `
        <td><input type="text" name="item_description[]" required oninput="updatePreview()"></td>
        <td><input type="number" name="item_quantity[]" step="0.01" min="0" required value="1" oninput="calcRow(this);updatePreview()"></td>
        <td><input type="number" name="item_price[]" step="0.01" min="0" required value="0" oninput="calcRow(this);updatePreview()"></td>
        <td class="row-amount">0.00</td>
        <td><button type="button" class="btn btn-sm btn-ghost" onclick="removeRow(this);updatePreview()" title="Remove"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></td>
    `;
    tbody.appendChild(row);
    row.querySelector('input').focus();
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.rows.length > 1) btn.closest('tr').remove();
}

function calcRow(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('input[name="item_quantity[]"]').value) || 0;
    const price = parseFloat(row.querySelector('input[name="item_price[]"]').value) || 0;
    row.querySelector('.row-amount').textContent = formatNum(qty * price);
    calcTotals();
}

function calcTotals() {
    let net = 0;
    document.querySelectorAll('.row-amount').forEach(el => net += parseFloat(el.textContent.replace(/,/g, '')) || 0);
    net = parseFloat(net.toFixed(2));
    const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
    const discountType = document.getElementById('discountType').value;
    const discountValue = parseFloat(document.getElementById('discountValue').value) || 0;
    let discountAmt = 0;
    if (discountType === 'percent') {
        discountAmt = parseFloat((net * (discountValue / 100)).toFixed(2));
    } else {
        discountAmt = parseFloat(discountValue.toFixed(2));
    }
    const taxable = net - discountAmt;
    const tax = parseFloat((taxable * (taxRate / 100)).toFixed(2));
    const gross = taxable + tax;
    document.getElementById('netTotal').textContent = formatNum(net);
    const discountLine = document.getElementById('discountLine');
    if (discountAmt > 0) {
        discountLine.style.display = '';
        document.getElementById('discountAmount').textContent = '- ' + formatNum(discountAmt);
    } else {
        discountLine.style.display = 'none';
    }
    document.getElementById('taxAmount').textContent = formatNum(tax);
    document.getElementById('grossTotal').textContent = formatNum(gross);
    return { net, discount: discountAmt, tax, gross };
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const upload = document.getElementById('logoUpload');
            upload.classList.add('has-logo');
            const placeholder = document.getElementById('logoPlaceholder');
            if (placeholder) placeholder.remove();
            let img = document.getElementById('logoPreview');
            if (!img) {
                img = document.createElement('img');
                img.id = 'logoPreview';
                upload.appendChild(img);
            }
            img.src = e.target.result;
            if (!document.getElementById('logoRemove')) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'logoRemove';
                btn.className = 'logo-remove';
                btn.innerHTML = '&times;';
                btn.title = 'Remove logo';
                btn.onclick = ev => { ev.stopPropagation(); removeLogo(); };
                upload.appendChild(btn);
            }
            document.getElementById('removeLogoField').value = '0';
            updatePreview();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeLogo() {
    const upload = document.getElementById('logoUpload');
    const img = document.getElementById('logoPreview');
    const btn = document.getElementById('logoRemove');
    if (img) img.remove();
    if (btn) btn.remove();
    upload.classList.remove('has-logo');
    const placeholder = document.createElement('div');
    placeholder.className = 'placeholder';
    placeholder.id = 'logoPlaceholder';
    placeholder.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>Click to upload logo`;
    upload.appendChild(placeholder);
    document.getElementById('logoInput').value = '';
    document.getElementById('removeLogoField').value = '1';
    updatePreview();
}

function updateLogoSize() { updatePreview(); }

function escHtml(s) { return s ? s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

function switchTemplate(tpl, btn) {
    currentTemplate = tpl;
    document.querySelectorAll('.template-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    updatePreview();
}

function getVal(id, fallback='') { return document.getElementById(id)?.value || fallback; }

function formatNum(n) {
    const parts = parseFloat(n).toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
}

function getItems() {
    const rows = document.querySelectorAll('.item-row');
    const items = [];
    rows.forEach(row => {
        const desc = row.querySelector('input[name="item_description[]"]')?.value || '';
        const qty = parseFloat(row.querySelector('input[name="item_quantity[]"]')?.value) || 0;
        const price = parseFloat(row.querySelector('input[name="item_price[]"]')?.value) || 0;
        if (desc) items.push({description: desc, quantity: qty, unit_price: price, amount: qty * price});
    });
    return items;
}

function formatMoney(amount, code) {
    const c = getCurrency(code);
    const num = parseFloat(amount);
    const parts = num.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return c.symbol + ' ' + parts.join('.');
}

function updatePreview() {
    const currency = getCurrency(currentCurrency);
    const items = getItems();
    const totals = calcTotals();
    const netTotal = totals.net;
    const discountAmt = totals.discount;
    const taxAmount = totals.tax;
    const grossTotal = totals.gross;
    const discountType = document.getElementById('discountType').value;
    const discountValue = parseFloat(getVal('discountValue', '0')) || 0;

    const logoEl = document.getElementById('logoPreview');
    const logoH = document.getElementById('logoHeight')?.value || 48;
    const logoHtml = logoEl ? `<img src="${logoEl.src}" style="max-height:${logoH}px;object-fit:contain">` : '';
    const statusColors = {draft:'#64748b',sent:'#2563eb',paid:'#16a34a',overdue:'#dc2626'};
    const statusBg = {draft:'#f1f5f9',sent:'#dbeafe',paid:'#dcfce7',overdue:'#fee2e2'};
    const status = getVal('status','draft');
    const sc = statusColors[status], sb = statusBg[status];
    const fromName = getVal('fromName') || 'Your Name';
    const fromAddr = getVal('fromAddress');
    const fromEmail = getVal('fromEmail');
    const fromVat = getVal('fromVat');
    const fromPhone = getVal('fromPhone');
    const fromAcct = getVal('fromAccount');
    const fromSwift = getVal('fromSwift');
    const toName = getVal('toName') || 'Client Name';
    const toAddr = getVal('toAddress');
    const toEmail = getVal('toEmail');
    const toVat = getVal('toVat');
    const toPhone = getVal('toPhone');
    const invNum = getVal('invoiceNumber');
    const dateIssue = getVal('dateOfIssue');
    const dateDue = getVal('dueDate');
    const notes = getVal('notes');
    const paymentMethod = getVal('paymentMethod');
    const paidAmount = parseFloat(getVal('paidAmount', '0')) || 0;
    const leftToPay = grossTotal - paidAmount;
    const qrData = getVal('qrData');
    const qrDesc = getVal('qrDescription');
    const qrShow = document.getElementById('qrShow')?.checked;

    function rows(striped) {
        if (!items.length) return `<tr><td colspan="5" style="padding:30px;text-align:center;color:#94a3b8;font-size:11px">No items added yet. Start adding items above.</td></tr>`;
        return items.map((item, i) => {
            const bg = striped && i % 2 === 0 ? 'background:#fafbfc;' : '';
            return `<tr style="${bg}">
                <td style="padding:10px 16px;font-size:11px;color:#94a3b8;border-bottom:1px solid #f1f5f9;width:5%">${i+1}</td>
                <td style="padding:10px 16px;font-size:11px;color:#0f172a;font-weight:500;border-bottom:1px solid #f1f5f9">${item.description}</td>
                <td style="padding:10px 16px;font-size:11px;text-align:center;color:#475569;border-bottom:1px solid #f1f5f9;width:8%">${item.quantity.toFixed(2)}</td>
                <td style="padding:10px 16px;font-size:11px;text-align:right;color:#475569;border-bottom:1px solid #f1f5f9;width:17%">${formatMoney(item.unit_price, currentCurrency)}</td>
                <td style="padding:10px 16px;font-size:11px;text-align:right;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;width:18%">${formatMoney(item.amount, currentCurrency)}</td>
            </tr>`;
        }).join('');
    }

    const taxRate = parseFloat(getVal('taxRate', '0')) || 0;

    function discountRow(bd) {
        if (discountAmt <= 0) return '';
        const label = discountType === 'percent' ? `Discount (${discountValue}%)` : 'Discount';
        return `<div style="display:flex;justify-content:space-between;padding:7px 0;font-size:11px;border-bottom:${bd || 'none'}"><span style="color:#64748b">${label}</span><span style="color:#dc2626;font-weight:500">- ${formatMoney(discountAmt, currentCurrency)}</span></div>`;
    }

    function taxRow(bd) {
        if (taxRate <= 0) return '';
        return `<div style="display:flex;justify-content:space-between;padding:7px 0;font-size:11px;border-bottom:${bd || 'none'}"><span style="color:#64748b">Tax (${taxRate}%)</span><span style="color:#475569">${formatMoney(taxAmount, currentCurrency)}</span></div>`;
    }

    function numberToWords(num) {
        if (num === 0) return 'Zero';
        const ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
        const tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
        function chunk(n) {
            if (n === 0) return '';
            if (n < 20) return ones[n];
            if (n < 100) return tens[Math.floor(n/10)] + (n%10 ? ' ' + ones[n%10] : '');
            return ones[Math.floor(n/100)] + ' Hundred' + (n%100 ? ' and ' + chunk(n%100) : '');
        }
        const currency = getCurrency(currentCurrency);
        const currencyName = currency.name || currency.code;
        const intPart = Math.floor(num);
        const decPart = Math.round((num - intPart) * 100);
        let result = '';
        if (intPart >= 1000000) {
            result += chunk(Math.floor(intPart / 1000000)) + ' Million ';
        }
        if (intPart >= 1000) {
            result += chunk(Math.floor((intPart % 1000000) / 1000)) + ' Thousand ';
        }
        result += chunk(intPart % 1000);
        result = result.trim() + ' ' + currencyName;
        if (decPart > 0) {
            result += ' and ' + decPart + '/100';
        }
        return result;
    }

    function paymentSummaryBlock(bg, tc) {
        return `
            <div style="padding:16px 36px;border-top:1px solid #f1f5f9;${bg ? 'background:' + bg + ';' : ''}">
                <div style="display:flex;gap:24px;flex-wrap:wrap">
                    <div style="flex:1;min-width:160px">
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:11px;color:${tc || '#64748b'}"><span>To pay</span><span style="font-weight:600;color:${tc === 'rgba(255,255,255,0.6)' ? '#fff' : '#0f172a'}">${formatMoney(grossTotal, currentCurrency)}</span></div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:11px;color:${tc || '#64748b'}"><span>Paid</span><span style="font-weight:600;color:#16a34a">${formatMoney(paidAmount, currentCurrency)}</span></div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:11px;border-top:1px solid ${tc === 'rgba(255,255,255,0.5)' ? 'rgba(255,255,255,0.1)' : '#f1f5f9'};margin-top:3px;padding-top:6px"><span style="font-weight:600;color:${tc || '#0f172a'}">Left to pay</span><span style="font-weight:700;color:${tc === 'rgba(255,255,255,0.6)' ? '#fff' : '#0f172a'};font-size:12px">${formatMoney(leftToPay, currentCurrency)}</span></div>
                    </div>
                    <div style="flex:1;min-width:200px">
                        <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.08em;color:${tc || '#94a3b8'};font-weight:600;margin-bottom:4px">Amount in words</div>
                        <div style="font-size:11px;color:${tc === 'rgba(255,255,255,0.6)' ? 'rgba(255,255,255,0.8)' : '#475569'};font-style:italic;line-height:1.5">${numberToWords(grossTotal)}</div>
                    </div>
                </div>
            </div>`;
    }

    const notesBlock = (notes || paymentMethod || fromAcct || fromSwift) ? `
        <div style="padding:16px 36px;border-top:1px solid #f1f5f9">
            <div style="display:flex;gap:24px">
                ${notes ? `<div style="flex:1">
                    <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;margin-bottom:4px">Notes</div>
                    <div style="font-size:11px;color:#475569;white-space:pre-wrap;line-height:1.6">${notes}</div>
                </div>` : ''}
                ${(paymentMethod || fromAcct || fromSwift) ? `<div style="flex:1">
                    <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;margin-bottom:4px">Payment Details</div>
                    ${paymentMethod ? `<div style="font-size:11px;color:#0f172a;font-weight:500;margin-bottom:3px">${paymentMethod}</div>` : ''}
                    ${fromAcct ? `<div style="font-size:10px;color:#64748b;margin-top:2px">Account: ${fromAcct}</div>` : ''}
                    ${fromSwift ? `<div style="font-size:10px;color:#64748b;margin-top:2px">SWIFT/BIC: ${fromSwift}</div>` : ''}
                </div>` : ''}
            </div>
        </div>` : '';

    const qrBlock = (qrShow && qrData) ? `
        <div style="padding:16px 36px;border-top:1px solid #f1f5f9;display:flex;gap:20px;align-items:flex-start">
            <div>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}" style="width:100px;height:100px;display:block" alt="QR Code">
                ${qrDesc ? `<div style="font-size:9px;color:#94a3b8;margin-top:4px;text-align:center;max-width:100px">${qrDesc}</div>` : ''}
            </div>
        </div>` : '';

    let html;
    if (currentTemplate === 'modern') {
        html = `<div style="font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,sans-serif;color:#0f172a;width:794px;min-height:1123px;display:flex;flex-direction:column;position:relative">
            <div style="background:linear-gradient(135deg,#1e293b 0%,#334155 50%,#475569 100%);padding:32px 36px 24px;position:relative;overflow:hidden">
                <div style="position:absolute;top:-30px;right:-30px;width:120px;height:120px;border-radius:50%;background:rgba(59,130,246,0.08)"></div>
                <div style="position:absolute;bottom:-40px;right:60px;width:80px;height:80px;border-radius:50%;background:rgba(139,92,246,0.06)"></div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1">
                    <div>
                        ${logoHtml ? `<div style="margin-bottom:14px">${logoHtml}</div>` : ''}
                        <div style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;line-height:1">INVOICE</div>
                        <div style="font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);letter-spacing:0px;text-transform:uppercase;margin-top:6px">${invNum}</div>
                    </div>
                    <div style="text-align:right">
                        <div style="display:inline-block;padding:3px 12px;border-radius:99px;font-size:10px;font-weight:700;background:${sb};color:${sc};text-transform:uppercase;letter-spacing:0.05em">${status}</div>
                        <div style="margin-top:12px;font-size:11px;color:rgba(255,255,255,0.6)"><span style="color:rgba(255,255,255,0.35)">Issued </span>${dateIssue}</div>
                        ${dateDue ? `<div style="margin-top:3px;font-size:11px;color:rgba(255,255,255,0.6)"><span style="color:rgba(255,255,255,0.35)">Due </span>${dateDue}</div>` : ''}
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:0;background:#fff;border-bottom:1px solid #f1f5f9">
                <div style="flex:1;padding:20px 36px">
                    <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.12em;color:#94a3b8;font-weight:700;margin-bottom:8px">Bill From</div>
                    <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:2px">${fromName}</div>
                    ${fromAddr ? `<div style="font-size:11px;color:#64748b;margin-top:3px;line-height:1.5">${fromAddr}</div>` : ''}
                    ${fromEmail ? `<div style="font-size:11px;color:#64748b;margin-top:2px">${fromEmail}</div>` : ''}
                    ${fromPhone ? `<div style="font-size:11px;color:#64748b;margin-top:2px">${fromPhone}</div>` : ''}
                    ${fromVat ? `<div style="font-size:10px;color:#94a3b8;margin-top:4px">Tax ID ${fromVat}</div>` : ''}
                </div>
                <div style="width:1px;background:#f1f5f9"></div>
                <div style="flex:1;padding:20px 36px">
                    <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.12em;color:#94a3b8;font-weight:700;margin-bottom:8px">Bill To</div>
                    <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:2px">${toName}</div>
                    ${toAddr ? `<div style="font-size:11px;color:#64748b;margin-top:3px;line-height:1.5">${toAddr}</div>` : ''}
                    ${toEmail ? `<div style="font-size:11px;color:#64748b;margin-top:2px">${toEmail}</div>` : ''}
                    ${toPhone ? `<div style="font-size:11px;color:#64748b;margin-top:2px">${toPhone}</div>` : ''}
                    ${toVat ? `<div style="font-size:10px;color:#94a3b8;margin-top:4px">Tax ID ${toVat}</div>` : ''}
                </div>
            </div>
            <div style="background:#fff;padding:0 36px">
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <th style="padding:10px 16px;text-align:left;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:5%">#</th>
                        <th style="padding:10px 16px;text-align:left;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:52%">Description</th>
                        <th style="padding:10px 16px;text-align:center;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:8%">Qty</th>
                        <th style="padding:10px 16px;text-align:right;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:17%">Price</th>
                        <th style="padding:10px 16px;text-align:right;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:18%">Amount</th>
                    </tr></thead>
                    <tbody>${rows(true)}</tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;padding:10px 36px 24px;background:#fff">
                <div style="width:260px">
                    <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:11px;color:#64748b;border-bottom:1px solid #f1f5f9"><span>Subtotal</span><span style="color:#0f172a;font-weight:500">${formatMoney(netTotal, currentCurrency)}</span></div>
                    ${discountRow('1px solid #f1f5f9')}
                    ${taxRow('1px solid #f1f5f9')}
                    <div style="display:flex;justify-content:space-between;padding:8px 0 0;margin-top:2px;border-top:1px solid #0f172a">
                        <span style="font-size:11px;font-weight:600;color:#0f172a">Total Due</span>
                        <span style="font-size:13px;font-weight:700;color:#0f172a">${formatMoney(grossTotal, currentCurrency)}</span>
                    </div>
                </div>
            </div>
            <div style="flex:1"></div>
            ${notesBlock}
            ${qrBlock}
            ${paymentSummaryBlock('', '')}
            <div style="padding:12px 36px;background:#f8fafc;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;margin-top:auto">
                <span style="font-size:10px;color:#94a3b8">${escHtml(getVal('footerText','Thank you for your business'))}</span>
                <span style="font-size:10px;color:#94a3b8">${invNum}</span>
            </div>
        </div>`;
    } else {
        html = `<div style="font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,sans-serif;color:#0f172a;width:794px;min-height:1123px;display:flex;flex-direction:column;position:relative">
            <div style="padding:32px 36px 24px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:22px;border-bottom:3px solid #0f172a">
                    <div>
                        ${logoHtml ? `<div style="margin-bottom:14px">${logoHtml}</div>` : ''}
                        <div style="font-size:20px;font-weight:800;color:#0f172a;letter-spacing:-0.5px;line-height:1">INVOICE</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:13px;font-weight:600;color:#67768b;letter-spacing:0px">${invNum}</div>
                        <div style="margin-top:8px"><span style="display:inline-block;padding:3px 12px;border-radius:99px;font-size:10px;font-weight:700;background:${sb};color:${sc};text-transform:uppercase;letter-spacing:0.05em">${status}</span></div>
                        <div style="margin-top:12px;font-size:11px;color:#64748b"><span style="color:#94a3b8">Issued</span> &nbsp;<strong style="color:#0f172a">${dateIssue}</strong></div>
                        ${dateDue ? `<div style="margin-top:3px;font-size:11px;color:#64748b"><span style="color:#94a3b8">Due</span> &nbsp;<strong style="color:#0f172a">${dateDue}</strong></div>` : ''}
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:0;padding:0 36px 24px">
                <div style="flex:1;padding:18px 24px;background:#f8fafc;border-radius:8px;border:1px solid #f1f5f9">
                    <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.14em;color:#94a3b8;font-weight:700;margin-bottom:8px">Bill From</div>
                    <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:2px">${fromName}</div>
                    ${fromAddr ? `<div style="font-size:11px;color:#64748b;margin-top:4px;line-height:1.5">${fromAddr}</div>` : ''}
                    ${fromEmail ? `<div style="font-size:11px;color:#64748b;margin-top:3px">${fromEmail}</div>` : ''}
                    ${fromPhone ? `<div style="font-size:11px;color:#64748b;margin-top:2px">${fromPhone}</div>` : ''}
                    ${fromVat ? `<div style="font-size:10px;color:#94a3b8;margin-top:6px">Tax ID ${fromVat}</div>` : ''}
                </div>
                <div style="width:20px"></div>
                <div style="flex:1;padding:18px 24px;background:#f8fafc;border-radius:8px;border:1px solid #f1f5f9">
                    <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.14em;color:#94a3b8;font-weight:700;margin-bottom:8px">Bill To</div>
                    <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:2px">${toName}</div>
                    ${toAddr ? `<div style="font-size:11px;color:#64748b;margin-top:4px;line-height:1.5">${toAddr}</div>` : ''}
                    ${toEmail ? `<div style="font-size:11px;color:#64748b;margin-top:3px">${toEmail}</div>` : ''}
                    ${toPhone ? `<div style="font-size:11px;color:#64748b;margin-top:2px">${toPhone}</div>` : ''}
                    ${toVat ? `<div style="font-size:10px;color:#94a3b8;margin-top:6px">Tax ID ${toVat}</div>` : ''}
                </div>
            </div>
            <div style="padding:0 36px">
                <table style="width:100%;border-collapse:collapse;border-radius:6px;overflow:hidden">
                    <thead><tr>
                        <th style="padding:10px 16px;text-align:left;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:5%">#</th>
                        <th style="padding:10px 16px;text-align:left;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:52%">Description</th>
                        <th style="padding:10px 16px;text-align:center;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:8%">Qty</th>
                        <th style="padding:10px 16px;text-align:right;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:17%">Price</th>
                        <th style="padding:10px 16px;text-align:right;font-size:9px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:18%">Amount</th>
                    </tr></thead>
                    <tbody>${rows(false)}</tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;padding:10px 36px 24px">
                <div style="width:260px">
                    <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:11px;color:#64748b;border-bottom:1px solid #f1f5f9"><span>Subtotal</span><span style="color:#0f172a;font-weight:500">${formatMoney(netTotal, currentCurrency)}</span></div>
                    ${discountRow('1px solid #f1f5f9')}
                    ${taxRow('1px solid #f1f5f9')}
                    <div style="display:flex;justify-content:space-between;padding:8px 0 0;margin-top:2px;border-top:1px solid #0f172a">
                        <span style="font-size:11px;font-weight:600;color:#0f172a">Total Due</span>
                        <span style="font-size:13px;font-weight:700;color:#0f172a">${formatMoney(grossTotal, currentCurrency)}</span>
                    </div>
                </div>
            </div>
            <div style="flex:1"></div>
            ${notesBlock}
            ${qrBlock}
            ${paymentSummaryBlock('', '')}
            <div style="padding:14px 36px;background:#0f172a;display:flex;justify-content:space-between;align-items:center;margin-top:auto">
                <span style="font-size:10px;color:rgba(255,255,255,0.5)">${escHtml(getVal('footerText','Thank you for your business'))}</span>
                <span style="font-size:10px;color:rgba(255,255,255,0.5)">${invNum}</span>
            </div>
        </div>`;
    }

    document.getElementById('previewContainer').innerHTML = html;
}

initCurrencyPicker();
calcTotals();
updatePreview();

function scalePreview() {
    const container = document.getElementById('previewContainer');
    const scroll = container.closest('.preview-scroll');
    if (!scroll || !container.firstElementChild) return;
    const available = scroll.clientWidth - 2;
    const content = container.firstElementChild;
    const natural = content.offsetWidth || 794;
    const scale = Math.min(1, available / natural);
    container.style.transform = `scale(${scale})`;
    container.style.height = (content.offsetHeight * scale) + 'px';
}
scalePreview();
window.addEventListener('resize', scalePreview);
</script>
</body>
</html>
