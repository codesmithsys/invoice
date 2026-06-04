<?php
require_once 'config.php';
$db = getDB();
if (!isset($_GET['token'])) { http_response_code(404); echo 'Invalid link'; exit; }
$stmt = $db->prepare("SELECT * FROM invoices WHERE share_token = ?");
$stmt->execute([$_GET['token']]);
$invoice = $stmt->fetch();
if (!$invoice) { http_response_code(404); echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:4rem"><h1>Link not found</h1><p>This invoice link is invalid or has been removed.</p></body></html>'; exit; }
$stmt2 = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order");
$stmt2->execute([$invoice['id']]);
$items = $stmt2->fetchAll();
$statusColors = ['draft'=>'#64748b','sent'=>'#2563eb','paid'=>'#16a34a','overdue'=>'#dc2626'];
$statusBg = ['draft'=>'#f1f5f9','sent'=>'#dbeafe','paid'=>'#dcfce7','overdue'=>'#fee2e2'];
$sc = $statusColors[$invoice['status']] ?? '#64748b';
$sb = $statusBg[$invoice['status']] ?? '#f1f5f9';
$s = htmlspecialchars($invoice['status']);
$cur = htmlspecialchars($invoice['currency']);
$curCode = $invoice['currency'];
$logo = $invoice['logo_path'] ? htmlspecialchars($invoice['logo_path']) : '';
$paidAmt = (float)($invoice['paid_amount'] ?? 0);
$gross = (float)$invoice['gross_total'];
$leftToPay = $gross - $paidAmt;
$discountAmt = 0;
if (!empty($invoice['discount_value']) && $invoice['discount_value'] > 0) {
    $discountAmt = ($invoice['discount_type'] ?? 'percent') === 'percent'
        ? (float)$invoice['net_total'] * ((float)$invoice['discount_value'] / 100)
        : (float)$invoice['discount_value'];
}
$invNum = htmlspecialchars($invoice['invoice_number']);
$dateIssue = htmlspecialchars($invoice['date_of_issue']);
$dateDue = htmlspecialchars($invoice['due_date'] ?? '');
$fromName = htmlspecialchars($invoice['from_name']);
$fromAddr = htmlspecialchars($invoice['from_address']);
$fromEmail = htmlspecialchars($invoice['from_email']);
$fromPhone = htmlspecialchars($invoice['from_phone'] ?? '');
$fromVat = htmlspecialchars($invoice['from_vat_no']);
$toName = htmlspecialchars($invoice['to_name']);
$toAddr = htmlspecialchars($invoice['to_address']);
$toEmail = htmlspecialchars($invoice['to_email']);
$toPhone = htmlspecialchars($invoice['to_phone'] ?? '');
$toVat = htmlspecialchars($invoice['to_vat_no']);
$netTotal = (float)$invoice['net_total'];
$grossTotal = (float)$invoice['gross_total'];
$notes = $invoice['notes'] ?? '';
$pm = htmlspecialchars($invoice['payment_method'] ?? '');
$acct = htmlspecialchars($invoice['from_account_number'] ?? '');
$swift = htmlspecialchars($invoice['from_swift_bic'] ?? '');
$qrData = $invoice['qr_data'] ?? '';
$qrDesc = htmlspecialchars($invoice['qr_description'] ?? '');
$qrShow = !empty($invoice['qr_show']);
$footerText = htmlspecialchars($invoice['footer_text'] ?: 'Thank you for your business');
$discountType = $invoice['discount_type'] ?? 'percent';
$discountValue = (float)($invoice['discount_value'] ?? 0);
$taxRate = (float)($invoice['tax_rate'] ?? 0);
$taxAmount = (float)($invoice['tax_amount'] ?? 0);

$currencyNames = [
    'USD'=>'US Dollar','AED'=>'UAE Dirham','AFN'=>'Afghani','ALL'=>'Lek','AMD'=>'Armenian Dram','ANG'=>'Netherlands Antillean Guilder',
    'AOA'=>'Kwanza','ARS'=>'Argentine Peso','AUD'=>'Australian Dollar','AWG'=>'Aruban Florin','AZN'=>'Azerbaijan Manat',
    'BAM'=>'Convertible Mark','BBD'=>'Barbados Dollar','BDT'=>'Taka','BGN'=>'Bulgarian Lev','BHD'=>'Bahraini Dinar',
    'BIF'=>'Burundi Franc','BMD'=>'Bermudian Dollar','BND'=>'Brunei Dollar','BOB'=>'Boliviano','BRL'=>'Brazilian Real',
    'BSD'=>'Bahamian Dollar','BTN'=>'Ngultrum','BWP'=>'Pula','BYN'=>'Belarusian Ruble','BZD'=>'Belize Dollar',
    'CAD'=>'Canadian Dollar','CDF'=>'Congolese Franc','CHF'=>'Swiss Franc','CLP'=>'Chilean Peso','CNY'=>'Yuan Renminbi',
    'COP'=>'Colombian Peso','CRC'=>'Costa Rican Colon','CUP'=>'Cuban Peso','CVE'=>'Cabo Verde Escudo','CZK'=>'Czech Koruna',
    'DJF'=>'Djibouti Franc','DKK'=>'Danish Krone','DOP'=>'Dominican Peso','DZD'=>'Algerian Dinar',
    'EGP'=>'Egyptian Pound','ERN'=>'Nakfa','ETB'=>'Ethiopian Birr','EUR'=>'Euro',
    'FJD'=>'Fiji Dollar','FKP'=>'Falkland Islands Pound',
    'GBP'=>'Pound Sterling','GEL'=>'Lari','GHS'=>'Ghana Cedi','GIP'=>'Gibraltar Pound','GMD'=>'Dalasi','GNF'=>'Guinean Franc',
    'GTQ'=>'Quetzal','GYD'=>'Guyana Dollar',
    'HKD'=>'Hong Kong Dollar','HNL'=>'Lempira','HRK'=>'Kuna','HTG'=>'Gourde','HUF'=>'Forint',
    'IDR'=>'Rupiah','ILS'=>'New Israeli Sheqel','INR'=>'Indian Rupee','ISK'=>'Iceland Krona',
    'JMD'=>'Jamaican Dollar','JOD'=>'Jordanian Dinar','JPY'=>'Yen',
    'KES'=>'Kenyan Shilling','KGS'=>'Som','KHR'=>'Riel','KMF'=>'Comorian Franc','KPW'=>'North Korean Won','KRW'=>'Won','KWD'=>'Kuwaiti Dinar','KYD'=>'Cayman Islands Dollar','KZT'=>'Tenge',
    'LAK'=>'Lao Kip','LBP'=>'Lebanese Pound','LKR'=>'Sri Lanka Rupee','LRD'=>'Liberian Dollar','LSL'=>'Loti','LYD'=>'Libyan Dinar',
    'MAD'=>'Moroccan Dirham','MDL'=>'Moldovan Leu','MGA'=>'Malagasy Ariary','MKD'=>'Denar','MMK'=>'Kyat','MNT'=>'Tugrik','MOP'=>'Pataca','MRU'=>'Ouguiya','MUR'=>'Mauritius Rupee','MVR'=>'Rufiyaa','MWK'=>'Malawi Kwacha','MXN'=>'Mexican Peso','MYR'=>'Malaysian Ringgit','MZN'=>'Mozambique Metical',
    'NAD'=>'Namibia Dollar','NGN'=>'Naira','NIO'=>'Cordoba Oro','NOK'=>'Norwegian Krone','NPR'=>'Nepalese Rupee','NZD'=>'New Zealand Dollar',
    'OMR'=>'Rial Omani',
    'PAB'=>'Balboa','PEN'=>'Sol','PGK'=>'Kina','PHP'=>'Philippine Peso','PKR'=>'Pakistan Rupee','PLN'=>'Zloty','PYG'=>'Guarani',
    'QAR'=>'Qatari Rial',
    'RON'=>'Romanian Leu','RSD'=>'Serbian Dinar','RUB'=>'Russian Ruble','RWF'=>'Rwandan Franc',
    'SAR'=>'Saudi Riyal','SBD'=>'Solomon Islands Dollar','SCR'=>'Seychelles Rupee','SDG'=>'Sudanese Pound','SEK'=>'Swedish Krona','SGD'=>'Singapore Dollar','SHP'=>'Saint Helena Pound','SLL'=>'Leone','SOS'=>'Somali Shilling','SRD'=>'Surinam Dollar','SSP'=>'South Sudanese Pound','STN'=>'Dobra','SVC'=>'El Salvador Colon','SYP'=>'Syrian Pound','SZL'=>'Lilangeni',
    'THB'=>'Baht','TJS'=>'Somoni','TMT'=>'Turkmenistan New Manat','TND'=>'Tunisian Dinar','TOP'=>'Pa\'anga','TRY'=>'Turkish Lira','TTD'=>'Trinidad and Tobago Dollar','TWD'=>'New Taiwan Dollar','TZS'=>'Tanzanian Shilling',
    'UAH'=>'Hryvnia','UGX'=>'Uganda Shilling','UYU'=>'Peso Uruguayo','UZS'=>'Uzbekistan Sum',
    'VES'=>'Bolivar Soberano','VND'=>'Dong','VUV'=>'Vatu',
    'WST'=>'Tala',
    'XAF'=>'CFA Franc BEAC','XCD'=>'East Caribbean Dollar','XOF'=>'CFA Franc BCEAO','XPF'=>'CFP Franc',
    'YER'=>'Yemeni Rial',
    'ZAR'=>'Rand','ZMW'=>'Zambian Kwacha','ZWL'=>'Zimbabwe Dollar'
];
function numberToWords($num) {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    if ($num == 0) return 'Zero';
    $num = (int)$num;
    $words = '';
    if ($num >= 1000000) { $words .= numberToWords((int)($num/1000000)) . ' Million '; $num %= 1000000; }
    if ($num >= 1000) { $words .= numberToWords((int)($num/1000)) . ' Thousand '; $num %= 1000; }
    if ($num >= 100) { $words .= $ones[(int)($num/100)] . ' Hundred '; $num %= 100; }
    if ($num >= 20) { $words .= $tens[(int)($num/10)] . ' '; $num %= 10; }
    if ($num > 0) { $words .= $ones[$num] . ' '; }
    return trim(preg_replace('/\s+/', ' ', $words));
}
$currencyName = $currencyNames[$curCode] ?? $curCode;
$amountInWords = numberToWords((int)$gross) . ' ' . $currencyName . (fmod($gross, 1) > 0 ? ' and ' . numberToWords((int)round(fmod($gross, 1) * 100)) . ' Cents' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $invNum ?> - Invoice</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <style>
    @page { margin: 0; size: A4 portrait; }
    @media print {
        .no-print{display:none !important}
        html,body{background:#fff;margin:0;padding:0}
        .public-wrap{padding:0 !important}
        .invoice-card{box-shadow:none !important;border:none !important;border-radius:0 !important;width:210mm !important;min-height:297mm !important;margin:0 !important;page-break-inside:avoid;break-inside:avoid}
        .invoice-card *{page-break-inside:avoid;break-inside:avoid}
    }
    body{background:var(--bg);font-family:var(--font)}
    .public-wrap{max-width:820px;margin:0 auto;padding:1.5rem}
    .public-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:.75rem 1rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.5rem}
    .public-bar .badge{display:inline-flex;align-items:center;gap:.4rem;padding:.25rem .65rem;background:var(--primary-light);color:var(--primary);border-radius:9999px;font-size:.75rem;font-weight:600}
    .public-bar .badge::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--primary)}
    </style>
</head>
<body>
    <div class="public-wrap">
        <div class="public-bar no-print">
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                <span class="badge">Shared Invoice</span>
                <span style="font-size:.85rem;color:var(--text-secondary)">Invoice <strong style="color:var(--text)"><?= $invNum ?></strong></span>
            </div>
            <div style="display:flex;gap:.5rem">
                <button onclick="window.print()" class="btn btn-primary btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Download PDF
                </button>
            </div>
        </div>

        <div class="invoice-card" style="font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,sans-serif;color:#0f172a;width:794px;min-height:1123px;display:flex;flex-direction:column;position:relative;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.06);margin:0 auto">
            <div style="padding:28px 36px 20px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:18px;border-bottom:3px solid #0f172a">
                    <div>
                        <?php if ($logo): ?><div style="margin-bottom:14px"><img src="<?= $logo ?>" style="max-height:48px;max-width:200px;object-fit:contain"></div><?php endif; ?>
                        <div style="font-size:20px;font-weight:800;color:#0f172a;letter-spacing:-0.5px;line-height:1">INVOICE</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:15px;font-weight:600;color:#475569;letter-spacing:0.5px"><?= $invNum ?></div>
                        <div style="margin-top:8px"><span style="display:inline-block;padding:3px 12px;border-radius:99px;font-size:10px;font-weight:700;background:<?= $sb ?>;color:<?= $sc ?>;text-transform:uppercase;letter-spacing:0.05em"><?= $s ?></span></div>
                        <div style="margin-top:12px;font-size:11px;color:#64748b"><span style="color:#94a3b8">Issued</span> &nbsp;<strong style="color:#0f172a"><?= $dateIssue ?></strong></div>
                        <?php if ($dateDue): ?><div style="margin-top:3px;font-size:11px;color:#64748b"><span style="color:#94a3b8">Due</span> &nbsp;<strong style="color:#0f172a"><?= $dateDue ?></strong></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:0;border-bottom:1px solid #f1f5f9">
                <div style="flex:1;padding:14px 36px">
                    <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.12em;color:#94a3b8;font-weight:700;margin-bottom:6px">Bill From</div>
                    <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:2px"><?= $fromName ?></div>
                    <?php if ($fromAddr): ?><div style="font-size:11px;color:#64748b;margin-top:2px;line-height:1.5"><?= nl2br($fromAddr) ?></div><?php endif; ?>
                    <?php if ($fromEmail): ?><div style="font-size:11px;color:#64748b;margin-top:2px"><?= $fromEmail ?></div><?php endif; ?>
                    <?php if ($fromPhone): ?><div style="font-size:11px;color:#64748b;margin-top:2px"><?= $fromPhone ?></div><?php endif; ?>
                    <?php if ($fromVat): ?><div style="font-size:10px;color:#94a3b8;margin-top:3px">Tax ID <?= $fromVat ?></div><?php endif; ?>
                </div>
                <div style="flex:1;padding:14px 36px">
                    <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.12em;color:#94a3b8;font-weight:700;margin-bottom:6px">Bill To</div>
                    <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:2px"><?= $toName ?></div>
                    <?php if ($toAddr): ?><div style="font-size:11px;color:#64748b;margin-top:2px;line-height:1.5"><?= nl2br($toAddr) ?></div><?php endif; ?>
                    <?php if ($toEmail): ?><div style="font-size:11px;color:#64748b;margin-top:2px"><?= $toEmail ?></div><?php endif; ?>
                    <?php if ($toPhone): ?><div style="font-size:11px;color:#64748b;margin-top:2px"><?= $toPhone ?></div><?php endif; ?>
                    <?php if ($toVat): ?><div style="font-size:10px;color:#94a3b8;margin-top:3px">Tax ID <?= $toVat ?></div><?php endif; ?>
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
                    <tbody>
                        <?php foreach ($items as $idx => $item): ?>
                            <tr>
                                <td style="padding:7px 16px;font-size:11px;color:#94a3b8;border-bottom:1px solid #f1f5f9"><?= $idx + 1 ?></td>
                                <td style="padding:7px 16px;font-size:11px;color:#0f172a;font-weight:500;border-bottom:1px solid #f1f5f9"><?= htmlspecialchars($item['description']) ?></td>
                                <td style="padding:7px 16px;font-size:11px;text-align:center;color:#475569;border-bottom:1px solid #f1f5f9"><?= number_format($item['quantity'], 2) ?></td>
                                <td style="padding:7px 16px;font-size:11px;text-align:right;color:#475569;border-bottom:1px solid #f1f5f9"><?= number_format($item['unit_price'], 2) ?></td>
                                <td style="padding:7px 16px;font-size:11px;text-align:right;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9"><?= number_format($item['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;padding:8px 36px 16px;background:#fff">
                <div style="width:260px">
                    <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:11px;color:#64748b;border-bottom:1px solid #f1f5f9"><span>Subtotal</span><span style="color:#0f172a;font-weight:500"><?= number_format($netTotal, 2) ?></span></div>
                    <?php if ($discountAmt > 0): ?>
                        <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:11px;color:#64748b;border-bottom:1px solid #f1f5f9">
                            <span>Discount<?= $discountType === 'percent' ? ' ('.number_format($discountValue, 2).'%)' : '' ?></span>
                            <span style="color:#dc2626;font-weight:500">-<?= number_format($discountAmt, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($taxRate > 0): ?>
                        <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:11px;color:#64748b;border-bottom:1px solid #f1f5f9">
                            <span>Tax (<?= number_format($taxRate, 2) ?>%)</span><span><?= number_format($taxAmount, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex;justify-content:space-between;padding:8px 0 0;margin-top:2px;border-top:1px solid #0f172a">
                        <span style="font-size:11px;font-weight:600;color:#0f172a">Total Due</span>
                        <span style="font-size:13px;font-weight:700;color:#0f172a"><?= number_format($grossTotal, 2) ?> <?= $cur ?></span>
                    </div>
                </div>
            </div>
            <div style="flex:1"></div>
            <?php if (!empty($notes) || !empty($pm) || !empty($acct) || !empty($swift)): ?>
                <div style="padding:12px 36px;border-top:1px solid #f1f5f9">
                    <div style="display:flex;gap:24px">
                        <?php if (!empty($notes)): ?>
                            <div style="flex:1">
                                <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;margin-bottom:4px">Notes</div>
                                <div style="font-size:11px;color:#475569;white-space:pre-wrap;line-height:1.6"><?= nl2br(htmlspecialchars($notes)) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($pm) || !empty($acct) || !empty($swift)): ?>
                            <div style="flex:1">
                                <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600;margin-bottom:4px">Payment Details</div>
                                <?php if (!empty($pm)): ?><div style="font-size:11px;color:#0f172a;font-weight:500;margin-bottom:3px"><?= $pm ?></div><?php endif; ?>
                                <?php if (!empty($acct)): ?><div style="font-size:10px;color:#64748b;margin-top:2px">Account: <?= $acct ?></div><?php endif; ?>
                                <?php if (!empty($swift)): ?><div style="font-size:10px;color:#64748b;margin-top:2px">SWIFT/BIC: <?= $swift ?></div><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($qrData) && $qrShow): ?>
                <div style="padding:12px 36px;border-top:1px solid #f1f5f9;display:flex;gap:20px;align-items:flex-start">
                    <div>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?= urlencode($qrData) ?>" style="width:80px;height:80px;display:block" alt="QR Code">
                        <?php if (!empty($qrDesc)): ?>
                            <div style="font-size:9px;color:#94a3b8;margin-top:4px;text-align:center;max-width:100px"><?= $qrDesc ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div style="padding:12px 36px;border-top:1px solid #f1f5f9">
                <div style="display:flex;gap:24px;flex-wrap:wrap">
                    <div style="flex:1;min-width:160px">
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:11px;color:#64748b"><span>To pay</span><span style="font-weight:600;color:#0f172a"><?= number_format($gross, 2) ?> <?= $cur ?></span></div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:11px;color:#64748b"><span>Paid</span><span style="font-weight:600;color:#16a34a"><?= number_format($paidAmt, 2) ?> <?= $cur ?></span></div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:11px;border-top:1px solid #f1f5f9;margin-top:3px;padding-top:6px"><span style="font-weight:600;color:#0f172a">Left to pay</span><span style="font-weight:700;color:#0f172a;font-size:12px"><?= number_format($leftToPay, 2) ?> <?= $cur ?></span></div>
                    </div>
                </div>
                <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #f1f5f9;font-size:10px;color:#64748b;font-style:italic">
                    <span style="color:#94a3b8">Amount in words:</span> <?= htmlspecialchars($amountInWords) ?>
                </div>
            </div>
            <div style="padding:10px 36px;background:#f8fafc;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;margin-top:auto">
                <span style="font-size:10px;color:#94a3b8"><?= $footerText ?></span>
                <span style="font-size:10px;color:#94a3b8"><?= $invNum ?></span>
            </div>
        </div>
    </div>
</body>
</html>
