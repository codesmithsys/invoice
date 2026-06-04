<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InvoiceApp</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
    <nav class="navbar">
        <a href="./" class="logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            InvoiceApp
        </a>
        <div class="nav-links">
            <a href="./">Invoices</a>
            <a href="create" class="btn btn-primary btn-sm">+ New Invoice</a>
        </div>
    </nav>

    <main class="container-center">
        <?php
        require_once 'config.php';
        $db = getDB();

        $total = $db->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
        $draft = $db->query("SELECT COUNT(*) FROM invoices WHERE status='draft'")->fetchColumn();
        $sent = $db->query("SELECT COUNT(*) FROM invoices WHERE status='sent'")->fetchColumn();
        $paid = $db->query("SELECT COUNT(*) FROM invoices WHERE status='paid'")->fetchColumn();
        $overdue = $db->query("SELECT COUNT(*) FROM invoices WHERE status='overdue'")->fetchColumn();
        $defaultCurrency = $db->query("SELECT COALESCE(currency,'KES') FROM invoices ORDER BY created_at DESC LIMIT 1")->fetchColumn() ?: 'KES';
        $totalRevenue = $db->query("SELECT COALESCE(SUM(gross_total),0) FROM invoices WHERE status='paid'")->fetchColumn();
        $totalPending = $db->query("SELECT COALESCE(SUM(gross_total),0) FROM invoices WHERE status IN ('draft','sent')")->fetchColumn();
        $totalOverdue = $db->query("SELECT COALESCE(SUM(gross_total),0) FROM invoices WHERE status='overdue'")->fetchColumn();
        ?>

        <div class="page-header">
            <div>
                <h1>Invoices</h1>
                <span class="subtitle"><?= $total ?> total invoices</span>
            </div>
            <div class="header-actions">
                <input type="text" class="search-input" placeholder="Search invoices..." id="searchInput" oninput="filterTable()">
                <div class="filters">
                    <select id="statusFilter" onchange="filterTable()">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="paid">Paid</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
                <a href="create" class="btn btn-primary">+ New Invoice</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="label">Total Revenue</span>
                <span class="value green"><?= htmlspecialchars($defaultCurrency) ?> <?= number_format($totalRevenue, 2) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">Pending</span>
                <span class="value blue"><?= htmlspecialchars($defaultCurrency) ?> <?= number_format($totalPending, 2) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">Overdue</span>
                <span class="value red"><?= htmlspecialchars($defaultCurrency) ?> <?= number_format($totalOverdue, 2) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">Paid</span>
                <span class="value green"><?= $paid ?> invoices</span>
            </div>
        </div>

        <table class="invoice-table" id="invoiceTable">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>From</th>
                    <th>To</th>
                    <th style="text-align:right">Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $db->query("SELECT * FROM invoices ORDER BY created_at DESC");
                $invoices = $stmt->fetchAll();

                if (empty($invoices)) {
                    echo '<tr><td colspan="7" class="empty-state">No invoices yet. <a href="create">Create your first invoice</a></td></tr>';
                } else {
                    foreach ($invoices as $inv) {
                        $s = htmlspecialchars($inv['status']);
                        $from = htmlspecialchars($inv['from_name'] ?: '—');
                        $to = htmlspecialchars($inv['to_name'] ?: '—');
                        echo '<tr data-status="'.$s.'" data-search="'.strtolower(implode(' ',[$inv['invoice_number'],$inv['from_name'],$inv['to_name']])).'">';
                        echo '<td><a href="view?id='.$inv['id'].'" style="color:var(--text);text-decoration:none;font-weight:600">'.htmlspecialchars($inv['invoice_number']).'</a></td>';
                        echo '<td>'.htmlspecialchars($inv['date_of_issue']).'</td>';
                        echo '<td>'.$from.'</td>';
                        echo '<td>'.$to.'</td>';
                        echo '<td class="amount" style="text-align:right">'.number_format($inv['gross_total'], 2).' '.htmlspecialchars($inv['currency']).'</td>';
                        echo '<td><span class="status-badge '.$s.'">'.ucfirst($s).'</span></td>';
                        echo '<td class="actions">';
                        echo '<a href="view?id='.$inv['id'].'" class="btn btn-sm btn-ghost" title="View"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a> ';
                        echo '<a href="create?id='.$inv['id'].'" class="btn btn-sm btn-ghost" title="Edit"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a> ';
                        echo '<a href="duplicate?id='.$inv['id'].'" class="btn btn-sm btn-ghost" title="Duplicate" onclick="return confirm(\'Duplicate this invoice?\')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></a> ';
                        echo '<a href="delete?id='.$inv['id'].'" class="btn btn-sm btn-ghost" title="Delete" onclick="return confirm(\'Delete this invoice?\')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>';
                        echo '</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </main>

    <script>
    function filterTable() {
        const status = document.getElementById('statusFilter').value.toLowerCase();
        const search = document.getElementById('searchInput').value.toLowerCase();
        document.querySelectorAll('#invoiceTable tbody tr').forEach(row => {
            if (!row.dataset.status) return;
            const matchStatus = !status || row.dataset.status === status;
            const matchSearch = !search || (row.dataset.search && row.dataset.search.includes(search));
            row.style.display = matchStatus && matchSearch ? '' : 'none';
        });
    }
    </script>
</body>
</html>
