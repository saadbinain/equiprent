<?php
require 'db.php';

$page_title    = 'Transactions';
$page_subtitle = 'View and search rental records';
$active_page   = 'transactions';

// ── Filters from GET ──────────────────────────────────────────
$search    = trim($_GET['search']    ?? '');
$status    = trim($_GET['status']    ?? '');
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to']   ?? '');

// ── Build query ───────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(r.customer_name LIKE ? OR e.name LIKE ? OR r.customer_phone LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status === 'rented') {
    $where[]  = "r.status = 'rented'";
} elseif ($status === 'returned') {
    $where[]  = "r.status = 'returned'";
} elseif ($status === 'overdue') {
    $where[]  = "r.status = 'rented' AND r.expected_return_date < CURDATE()";
}

if ($dateFrom !== '') {
    $where[]  = 'r.rent_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]  = 'r.rent_date <= ?';
    $params[] = $dateTo;
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT r.*,
           e.name AS equipment_name,
           CASE
               WHEN r.status = 'rented' AND r.expected_return_date < CURDATE()
               THEN 'overdue'
               ELSE r.status
           END AS display_status
    FROM rentals r
    JOIN equipment e ON r.equipment_id = e.id
    WHERE $whereClause
    ORDER BY r.id DESC
");
$stmt->execute($params);
$rentals = $stmt->fetchAll();

// ── Totals for the filtered result ────────────────────────────
$filteredTotal = array_sum(array_column(
    array_filter($rentals, fn($r) => $r['status'] === 'returned'),
    'total_amount'
));

require 'header.php';
?>

<!-- ── Filter Bar ─────────────────────────────────────────── -->
<form method="GET" action="transactions.php">
    <div class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Customer name, equipment, phone…"
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="rented"   <?= $status === 'rented'   ? 'selected' : '' ?>>Rented</option>
                    <option value="returned" <?= $status === 'returned' ? 'selected' : '' ?>>Returned</option>
                    <option value="overdue"  <?= $status === 'overdue'  ? 'selected' : '' ?>>Overdue</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control"
                       value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control"
                       value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="transactions.php" class="btn btn-outline-secondary" title="Clear">
                    <i class="fas fa-xmark"></i>
                </a>
            </div>
        </div>
    </div>
</form>

<!-- ── Summary strip ─────────────────────────────────────────── -->
<div class="d-flex flex-wrap gap-3 mb-3" style="font-size:13px;color:#64748b;">
    <span><i class="fas fa-list me-1"></i> <strong><?= count($rentals) ?></strong> records found</span>
    <?php if ($filteredTotal > 0): ?>
    <span><i class="fas fa-peso-sign me-1"></i> Revenue: <strong>&#8369;<?= number_format($filteredTotal, 2) ?></strong></span>
    <?php endif; ?>
    <?php if ($search || $status || $dateFrom || $dateTo): ?>
    <span class="text-primary"><i class="fas fa-filter me-1"></i>Filters active</span>
    <?php endif; ?>
</div>

<!-- ── Transactions Table ─────────────────────────────────────── -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list-check text-primary"></i> Rental Transactions</span>
        <a href="rent.php" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> New Rental
        </a>
    </div>
    <div class="table-wrapper">
        <?php if (empty($rentals)): ?>
        <div class="empty-state">
            <i class="fas fa-magnifying-glass d-block"></i>
            No transactions match your search criteria.
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>Daily Rate</th>
                    <th>Rent Date</th>
                    <th>Exp. Return</th>
                    <th>Actual Return</th>
                    <th>Days</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rentals as $r): ?>
                <tr>
                    <td><span class="rental-id">#<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                    <td>
                        <strong><?= htmlspecialchars($r['customer_name']) ?></strong>
                        <?php if ($r['customer_phone']): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($r['customer_phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td><?= (int)$r['quantity'] ?></td>
                    <td>&#8369;<?= number_format((float)$r['daily_rate'], 2) ?></td>
                    <td><?= htmlspecialchars($r['rent_date']) ?></td>
                    <td>
                        <?= htmlspecialchars($r['expected_return_date']) ?>
                        <?php
                        $today = date('Y-m-d');
                        if ($r['status'] === 'rented' && $r['expected_return_date'] < $today):
                            $late = (new DateTime($today))->diff(new DateTime($r['expected_return_date']))->days;
                        ?>
                        <br><small class="text-danger fw-semibold"><?= $late ?> day<?= $late > 1 ? 's' : '' ?> overdue</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $r['actual_return_date']
                            ? htmlspecialchars($r['actual_return_date'])
                            : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td><?= $r['total_days'] !== null ? (int)$r['total_days'] : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <?= $r['total_amount'] !== null
                            ? '<strong>&#8369;' . number_format((float)$r['total_amount'], 2) . '</strong>'
                            : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td>
                        <?php if ($r['display_status'] === 'overdue'): ?>
                            <span class="badge-overdue">Overdue</span>
                        <?php elseif ($r['display_status'] === 'rented'): ?>
                            <span class="badge-rented">Rented</span>
                        <?php else: ?>
                            <span class="badge-returned">Returned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'rented'): ?>
                        <a href="return.php?id=<?= (int)$r['id'] ?>"
                           class="btn btn-sm btn-outline-success" title="Process Return">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
