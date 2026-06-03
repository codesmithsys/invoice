<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($_GET['id']) ? 'Edit' : 'New' ?> Invoice</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
    <nav class="navbar no-print">
        <a href="index.php" class="logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            InvoiceApp
        </a>
        <div class="nav-links">
            <a href="index.php">Invoices</a>
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
        $grossTotal = $netTotal + $taxAmount;

        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                $logoName = 'logo_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/uploads/' . $logoName);
                $logoPath = 'uploads/' . $logoName;
            }
        }

        if ($invoiceId && $logoPath === null && !empty($data['existing_logo'])) {
            $logoPath = $data['existing_logo'];
        }

        $notes = $data['notes'] ?? '';
        $paymentMethod = $data['payment_method'] ?? '';

        if ($invoiceId) {
            $sql = "UPDATE invoices SET invoice_number=?, date_of_issue=?, due_date=?,
                from_name=?, from_address=?, from_email=?, from_vat_no=?, from_account_number=?, from_swift_bic=?,
                to_name=?, to_address=?, to_email=?, to_vat_no=?,
                currency=?, tax_rate=?, tax_amount=?, net_total=?, gross_total=?,
                status=?, notes=?, payment_method=?, logo_path=? WHERE id=?";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['invoice_number'], $data['date_of_issue'], $data['due_date'] ?: null,
                $data['from_name'], $data['from_address'], $data['from_email'], $data['from_vat_no'], $data['from_account_number'], $data['from_swift_bic'],
                $data['to_name'], $data['to_address'], $data['to_email'], $data['to_vat_no'],
                $data['currency'], $taxRate, $taxAmount, $netTotal, $grossTotal,
                $data['status'], $notes, $paymentMethod, $logoPath, $invoiceId
            ]);
            $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoiceId]);
        } else {
            $sql = "INSERT INTO invoices SET invoice_number=?, date_of_issue=?, due_date=?,
                from_name=?, from_address=?, from_email=?, from_vat_no=?, from_account_number=?, from_swift_bic=?,
                to_name=?, to_address=?, to_email=?, to_vat_no=?,
                currency=?, tax_rate=?, tax_amount=?, net_total=?, gross_total=?,
                status=?, notes=?, payment_method=?, logo_path=?";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['invoice_number'], $data['date_of_issue'], $data['due_date'] ?: null,
                $data['from_name'], $data['from_address'], $data['from_email'], $data['from_vat_no'], $data['from_account_number'], $data['from_swift_bic'],
                $data['to_name'], $data['to_address'], $data['to_email'], $data['to_vat_no'],
                $data['currency'], $taxRate, $taxAmount, $netTotal, $grossTotal,
                $data['status'], $notes, $paymentMethod, $logoPath
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
        header("Location: view.php?id=$invoiceId");
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
                                <?php else: ?>
                                    <div class="placeholder" id="logoPlaceholder">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        Click to upload logo
                                    </div>
                                <?php endif; ?>
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
                                <label>KRA Pin</label>
                                <input type="text" name="from_vat_no" id="fromVat"
                                    value="<?= $val('from_vat_no') ?>" oninput="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label>Account Number</label>
                                <input type="text" name="from_account_number" id="fromAccount"
                                    value="<?= $val('from_account_number') ?>" oninput="updatePreview()">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>SWIFT / BIC</label>
                            <input type="text" name="from_swift_bic" id="fromSwift"
                                value="<?= $val('from_swift_bic') ?>" oninput="updatePreview()">
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
                        <div class="form-group">
                            <label>KRA Pin</label>
                            <input type="text" name="to_vat_no" id="toVat"
                                value="<?= $val('to_vat_no') ?>" oninput="updatePreview()">
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

                <!-- Notes & Tax -->
                <div class="form-section">
                    <div class="form-section-title">Notes, Payment & Tax</div>
                    <div class="form-section-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Notes (visible on invoice)</label>
                                <textarea name="notes" id="notes" rows="3" oninput="updatePreview()"><?= $val('notes') ?></textarea>
                            </div>
                            <div>
                                <div class="form-group">
                                    <label>Payment Method</label>
                                    <input type="text" name="payment_method" id="paymentMethod" placeholder="e.g. M-Pesa, Bank Transfer, PayPal"
                                        value="<?= $val('payment_method') ?>" oninput="updatePreview()">
                                </div>
                                <div class="form-group">
                                    <label>Tax Rate (%)</label>
                                    <input type="number" name="tax_rate" id="taxRate" step="0.01" min="0" max="100"
                                        value="<?= $val('tax_rate', '0') ?>" oninput="calcTotals();updatePreview()">
                                </div>
                                <div class="totals-card">
                                    <div class="total-line"><span>Net Total</span><strong id="netTotal">0.00</strong></div>
                                    <div class="total-line"><span>Tax</span><span id="taxAmount">0.00</span></div>
                                    <div class="total-line total-gross"><span>Gross Total</span><strong id="grossTotal">0.00</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <a href="index.php" class="btn btn-outline">Cancel</a>
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
    const content = document.getElementById('previewContainer').innerHTML;
    const win = window.open('','','width=900,height=700');
    win.document.write(`<!DOCTYPE html><html><head><title>Invoice</title>
        <style>
          @page{margin:0;size:A4}
          html,body{margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
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
    row.querySelector('.row-amount').textContent = (qty * price).toFixed(2);
    calcTotals();
}

function calcTotals() {
    let net = 0;
    document.querySelectorAll('.row-amount').forEach(el => net += parseFloat(el.textContent) || 0);
    const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
    const tax = net * (taxRate / 100);
    document.getElementById('netTotal').textContent = net.toFixed(2);
    document.getElementById('taxAmount').textContent = tax.toFixed(2);
    document.getElementById('grossTotal').textContent = (net + tax).toFixed(2);
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const upload = document.getElementById('logoUpload');
            upload.classList.add('has-logo');
            upload.innerHTML = `<input type="file" name="logo" id="logoInput" accept="image/*" onchange="previewLogo(this)"><img src="${e.target.result}" id="logoPreview">`;
            updatePreview();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function switchTemplate(tpl, btn) {
    currentTemplate = tpl;
    document.querySelectorAll('.template-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    updatePreview();
}

function getVal(id, fallback='') { return document.getElementById(id)?.value || fallback; }

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
    return c.symbol + parseFloat(amount).toFixed(2);
}

function updatePreview() {
    const currency = getCurrency(currentCurrency);
    const items = getItems();
    const netTotal = items.reduce((s,i) => s + i.amount, 0);
    const taxRate = parseFloat(getVal('taxRate', '0')) || 0;
    const taxAmount = netTotal * (taxRate / 100);
    const grossTotal = netTotal + taxAmount;
    calcTotals();

    const logoEl = document.getElementById('logoPreview');
    const logoHtml = logoEl ? `<img src="${logoEl.src}" style="max-height:52px;object-fit:contain">` : '';
    const statusColors = {draft:'#64748b',sent:'#2563eb',paid:'#16a34a',overdue:'#dc2626'};
    const statusBg = {draft:'#f1f5f9',sent:'#dbeafe',paid:'#dcfce7',overdue:'#fee2e2'};
    const status = getVal('status','draft');
    const sc = statusColors[status], sb = statusBg[status];
    const fromName = getVal('fromName') || 'Your Name';
    const fromAddr = getVal('fromAddress');
    const fromEmail = getVal('fromEmail');
    const fromVat = getVal('fromVat');
    const fromAcct = getVal('fromAccount');
    const fromSwift = getVal('fromSwift');
    const toName = getVal('toName') || 'Client Name';
    const toAddr = getVal('toAddress');
    const toEmail = getVal('toEmail');
    const toVat = getVal('toVat');
    const invNum = getVal('invoiceNumber');
    const dateIssue = getVal('dateOfIssue');
    const dateDue = getVal('dueDate');
    const notes = getVal('notes');
    const paymentMethod = getVal('paymentMethod');

    function rows(striped) {
        if (!items.length) return `<tr><td colspan="5" style="padding:40px;text-align:center;color:#94a3b8;font-size:13px">No items added yet. Start adding items above.</td></tr>`;
        return items.map((item, i) => {
            const bg = striped && i % 2 === 0 ? 'background:#fafbfc;' : '';
            return `<tr style="${bg}">
                <td style="padding:13px 20px;font-size:13px;color:#94a3b8;border-bottom:1px solid #f1f5f9;width:5%">${i+1}</td>
                <td style="padding:13px 20px;font-size:13px;color:#0f172a;font-weight:500;border-bottom:1px solid #f1f5f9">${item.description}</td>
                <td style="padding:13px 20px;font-size:13px;text-align:center;color:#475569;border-bottom:1px solid #f1f5f9;width:8%">${item.quantity.toFixed(2)}</td>
                <td style="padding:13px 20px;font-size:13px;text-align:right;color:#475569;border-bottom:1px solid #f1f5f9;width:17%">${formatMoney(item.unit_price, currentCurrency)}</td>
                <td style="padding:13px 20px;font-size:13px;text-align:right;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;width:18%">${formatMoney(item.amount, currentCurrency)}</td>
            </tr>`;
        }).join('');
    }

    function taxRow(bd) {
        if (taxRate <= 0) return '';
        return `<tr><td colspan="4" style="padding:10px 20px;text-align:right;font-size:13px;color:#64748b;${bd}">Tax (${taxRate}%)</td><td style="padding:10px 20px;text-align:right;font-size:13px;color:#475569;${bd}">${formatMoney(taxAmount, currentCurrency)}</td></tr>`;
    }

    const notesBlock = (notes || paymentMethod) ? `
        <div style="padding:24px 40px;border-top:1px solid #f1f5f9">
            <div style="display:flex;gap:24px">
                ${notes ? `<div style="flex:1">
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;margin-bottom:6px">Notes</div>
                    <div style="font-size:13px;color:#475569;white-space:pre-wrap;line-height:1.7">${notes}</div>
                </div>` : ''}
                ${paymentMethod ? `<div style="flex:1">
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;margin-bottom:6px">Payment Method</div>
                    <div style="font-size:13px;color:#475569;line-height:1.7">${paymentMethod}</div>
                </div>` : ''}
            </div>
        </div>` : '';

    let html;
    if (currentTemplate === 'modern') {
        html = `<div style="font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,sans-serif;color:#0f172a;min-height:100%">
            <div style="background:linear-gradient(135deg,#1e293b 0%,#334155 50%,#475569 100%);padding:36px 40px 28px;position:relative;overflow:hidden">
                <div style="position:absolute;top:-30px;right:-30px;width:120px;height:120px;border-radius:50%;background:rgba(59,130,246,0.08)"></div>
                <div style="position:absolute;bottom:-40px;right:60px;width:80px;height:80px;border-radius:50%;background:rgba(139,92,246,0.06)"></div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1">
                    <div>
                        ${logoHtml ? `<div style="margin-bottom:18px">${logoHtml}</div>` : ''}
                        <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,0.5);letter-spacing:4px;text-transform:uppercase">Invoice</div>
                        <div style="font-size:24px;font-weight:300;color:#fff;margin-top:2px;letter-spacing:1px">${invNum}</div>
                    </div>
                    <div style="text-align:right">
                        <div style="display:inline-block;padding:4px 16px;border-radius:99px;font-size:11px;font-weight:700;background:${sb};color:${sc};text-transform:uppercase;letter-spacing:0.05em">${status}</div>
                        <div style="margin-top:16px;font-size:13px;color:rgba(255,255,255,0.6)"><span style="color:rgba(255,255,255,0.35)">Issued </span>${dateIssue}</div>
                        ${dateDue ? `<div style="margin-top:4px;font-size:13px;color:rgba(255,255,255,0.6)"><span style="color:rgba(255,255,255,0.35)">Due </span>${dateDue}</div>` : ''}
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:0;background:#fff;border-bottom:1px solid #f1f5f9">
                <div style="flex:1;padding:28px 40px">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:0.12em;color:#94a3b8;font-weight:700;margin-bottom:10px">Bill From</div>
                    <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:2px">${fromName}</div>
                    ${fromAddr ? `<div style="font-size:13px;color:#64748b;margin-top:4px;line-height:1.6">${fromAddr}</div>` : ''}
                    ${fromEmail ? `<div style="font-size:13px;color:#64748b;margin-top:3px">${fromEmail}</div>` : ''}
                    ${fromVat ? `<div style="font-size:12px;color:#94a3b8;margin-top:6px">KRA Pin ${fromVat}</div>` : ''}
                    ${fromAcct ? `<div style="font-size:12px;color:#94a3b8;margin-top:4px">Account: ${fromAcct}</div>` : ''}
                    ${fromSwift ? `<div style="font-size:12px;color:#94a3b8;margin-top:4px">SWIFT/BIC: ${fromSwift}</div>` : ''}
                </div>
                <div style="width:1px;background:#f1f5f9"></div>
                <div style="flex:1;padding:28px 40px">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:0.12em;color:#94a3b8;font-weight:700;margin-bottom:10px">Bill To</div>
                    <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:2px">${toName}</div>
                    ${toAddr ? `<div style="font-size:13px;color:#64748b;margin-top:4px;line-height:1.6">${toAddr}</div>` : ''}
                    ${toEmail ? `<div style="font-size:13px;color:#64748b;margin-top:3px">${toEmail}</div>` : ''}
                    ${toVat ? `<div style="font-size:12px;color:#94a3b8;margin-top:6px">KRA Pin ${toVat}</div>` : ''}
                </div>
            </div>
            <div style="background:#fff;padding:0 40px">
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <th style="padding:12px 20px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:5%">#</th>
                        <th style="padding:12px 20px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:52%">Description</th>
                        <th style="padding:12px 20px;text-align:center;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:8%">Qty</th>
                        <th style="padding:12px 20px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:17%">Price</th>
                        <th style="padding:12px 20px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;border-bottom:2px solid #e2e8f0;width:18%">Amount</th>
                    </tr></thead>
                    <tbody>${rows(true)}</tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;padding:12px 40px 32px;background:#fff">
                <div style="width:300px">
                    <div style="display:flex;justify-content:space-between;padding:9px 0;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9"><span>Subtotal</span><span style="color:#0f172a;font-weight:500">${formatMoney(netTotal, currentCurrency)}</span></div>
                    ${taxRow('border-bottom:1px solid #f1f5f9;')}
                    <div style="display:flex;justify-content:space-between;padding:16px 0 8px;margin-top:4px">
                        <div>
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:#94a3b8;font-weight:600">Total Due</div>
                            <div style="font-size:22px;font-weight:800;color:#0f172a;margin-top:2px">${formatMoney(grossTotal, currentCurrency)}</div>
                        </div>
                    </div>
                </div>
            </div>
            ${notesBlock}
            <div style="padding:16px 40px;background:#f8fafc;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:11px;color:#94a3b8">Thank you for your business</span>
                <span style="font-size:11px;color:#94a3b8">${invNum}</span>
            </div>
        </div>`;
    } else {
        html = `<div style="font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,sans-serif;color:#0f172a;min-height:100%">
            <div style="padding:40px 40px 32px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:28px;border-bottom:3px solid #0f172a">
                    <div>
                        ${logoHtml ? `<div style="margin-bottom:20px">${logoHtml}</div>` : ''}
                        <div style="font-size:42px;font-weight:900;color:#0f172a;letter-spacing:-1.5px;line-height:1">INVOICE</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:18px;font-weight:600;color:#475569;letter-spacing:0.5px">${invNum}</div>
                        <div style="margin-top:10px"><span style="display:inline-block;padding:4px 16px;border-radius:99px;font-size:11px;font-weight:700;background:${sb};color:${sc};text-transform:uppercase;letter-spacing:0.05em">${status}</span></div>
                        <div style="margin-top:16px;font-size:13px;color:#64748b"><span style="color:#94a3b8">Issued</span> &nbsp;<strong style="color:#0f172a">${dateIssue}</strong></div>
                        ${dateDue ? `<div style="margin-top:4px;font-size:13px;color:#64748b"><span style="color:#94a3b8">Due</span> &nbsp;<strong style="color:#0f172a">${dateDue}</strong></div>` : ''}
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:0;padding:0 40px 32px">
                <div style="flex:1;padding:24px 28px;background:#f8fafc;border-radius:10px;border:1px solid #f1f5f9">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:0.14em;color:#94a3b8;font-weight:700;margin-bottom:12px">Bill From</div>
                    <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px">${fromName}</div>
                    ${fromAddr ? `<div style="font-size:13px;color:#64748b;margin-top:6px;line-height:1.6">${fromAddr}</div>` : ''}
                    ${fromEmail ? `<div style="font-size:13px;color:#64748b;margin-top:4px">${fromEmail}</div>` : ''}
                    ${fromVat ? `<div style="font-size:12px;color:#94a3b8;margin-top:8px">KRA Pin ${fromVat}</div>` : ''}
                    ${fromAcct ? `<div style="font-size:12px;color:#94a3b8;margin-top:4px">Account: ${fromAcct}</div>` : ''}
                    ${fromSwift ? `<div style="font-size:12px;color:#94a3b8;margin-top:4px">SWIFT/BIC: ${fromSwift}</div>` : ''}
                </div>
                <div style="width:24px"></div>
                <div style="flex:1;padding:24px 28px;background:#f8fafc;border-radius:10px;border:1px solid #f1f5f9">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:0.14em;color:#94a3b8;font-weight:700;margin-bottom:12px">Bill To</div>
                    <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px">${toName}</div>
                    ${toAddr ? `<div style="font-size:13px;color:#64748b;margin-top:6px;line-height:1.6">${toAddr}</div>` : ''}
                    ${toEmail ? `<div style="font-size:13px;color:#64748b;margin-top:4px">${toEmail}</div>` : ''}
                    ${toVat ? `<div style="font-size:12px;color:#94a3b8;margin-top:8px">KRA Pin ${toVat}</div>` : ''}
                </div>
            </div>
            <div style="padding:0 40px">
                <table style="width:100%;border-collapse:collapse;border-radius:8px;overflow:hidden">
                    <thead><tr>
                        <th style="padding:13px 20px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:5%">#</th>
                        <th style="padding:13px 20px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:52%">Description</th>
                        <th style="padding:13px 20px;text-align:center;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:8%">Qty</th>
                        <th style="padding:13px 20px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:17%">Price</th>
                        <th style="padding:13px 20px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#fff;font-weight:600;background:#0f172a;width:18%">Amount</th>
                    </tr></thead>
                    <tbody>${rows(false)}</tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;padding:12px 40px 36px">
                <div style="width:300px">
                    <div style="display:flex;justify-content:space-between;padding:10px 20px;font-size:13px;color:#64748b;background:#f8fafc;border-radius:6px 6px 0 0;border-bottom:1px solid #f1f5f9"><span>Subtotal</span><span style="color:#0f172a;font-weight:500">${formatMoney(netTotal, currentCurrency)}</span></div>
                    ${taxRow('')}
                    <div style="display:flex;justify-content:space-between;padding:14px 20px;background:#0f172a;border-radius:0 0 6px 6px">
                        <div>
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:rgba(255,255,255,0.5);font-weight:600">Total Due</div>
                            <div style="font-size:22px;font-weight:800;color:#fff;margin-top:2px">${formatMoney(grossTotal, currentCurrency)}</div>
                        </div>
                    </div>
                </div>
            </div>
            ${notesBlock}
            <div style="padding:18px 40px;background:#0f172a;display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:11px;color:rgba(255,255,255,0.5)">Thank you for your business</span>
                <span style="font-size:11px;color:rgba(255,255,255,0.5)">${invNum}</span>
            </div>
        </div>`;
    }

    document.getElementById('previewContainer').innerHTML = html;
}

initCurrencyPicker();
calcTotals();
updatePreview();
</script>
</body>
</html>
