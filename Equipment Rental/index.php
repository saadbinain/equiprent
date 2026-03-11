<?php
require 'db.php';

$page_title    = 'Dashboard';
$page_subtitle = 'Overview of your rental operations';
$active_page   = 'dashboard';

// ── Dashboard statistics ─────────────────────────────────────
$totalEquipment     = (int) $pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
$availableEquipment = (int) $pdo->query("SELECT COUNT(*) FROM equipment WHERE available_quantity > 0")->fetchColumn();
$activeRentals      = (int) $pdo->query("SELECT COUNT(*) FROM rentals WHERE status = 'rented'")->fetchColumn();
$overdueRentals     = (int) $pdo->query("SELECT COUNT(*) FROM rentals WHERE status = 'rented' AND expected_return_date < CURDATE()")->fetchColumn();
$totalRevenue       = (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM rentals WHERE status = 'returned'")->fetchColumn();
$totalTransactions  = (int) $pdo->query("SELECT COUNT(*) FROM rentals")->fetchColumn();

// ── Recent rentals (last 10) ──────────────────────────────────
$recentRentals = $pdo->query("
    SELECT r.id, r.customer_name, r.quantity, r.rent_date,
           r.expected_return_date, r.total_amount, r.status,
           e.name AS equipment_name,
           CASE
               WHEN r.status = 'rented' AND r.expected_return_date < CURDATE()
               THEN 'overdue'
               ELSE r.status
           END AS display_status
    FROM rentals r
    JOIN equipment e ON r.equipment_id = e.id
    ORDER BY r.created_at DESC
    LIMIT 10
")->fetchAll();

require 'header.php';
?>

<!-- ── Stat cards ────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card bg-blue">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-0">Total Equipment</p>
                    <p class="stat-value"><?= $totalEquipment ?></p>
                    <p class="stat-change mb-0"><i class="fas fa-box me-1"></i><?= $availableEquipment ?> currently available</p>
                </div>
                <div class="stat-icon"><i class="fas fa-toolbox"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card bg-amber">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-0">Active Rentals</p>
                    <p class="stat-value"><?= $activeRentals ?></p>
                    <p class="stat-change mb-0">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        <?= $overdueRentals ?> overdue
                    </p>
                </div>
                <div class="stat-icon"><i class="fas fa-hand-holding"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card bg-green">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-0">Total Revenue</p>
                    <p class="stat-value" style="font-size:22px;">&#8369;<?= number_format($totalRevenue, 2) ?></p>
                    <p class="stat-change mb-0"><i class="fas fa-receipt me-1"></i>from returned rentals</p>
                </div>
                <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card bg-purple">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-0">Total Transactions</p>
                    <p class="stat-value"><?= $totalTransactions ?></p>
                    <p class="stat-change mb-0"><i class="fas fa-calendar me-1"></i>all time</p>
                </div>
                <div class="stat-icon"><i class="fas fa-list-check"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- ── Quick Actions ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt text-warning"></i> Quick Actions</div>
            <div class="card-body d-flex flex-wrap gap-2 p-3">
                <a href="rent.php" class="btn btn-primary">
                    <i class="fas fa-hand-holding me-1"></i> New Rental
                </a>
                <a href="return.php" class="btn btn-success">
                    <i class="fas fa-rotate-left me-1"></i> Process Return
                </a>
                <a href="equipment.php" class="btn btn-outline-secondary">
                    <i class="fas fa-plus me-1"></i> Add Equipment
                </a>
                <a href="transactions.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list-check me-1"></i> View All Transactions
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Transactions ───────────────────────────────────── -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clock-rotate-left text-primary"></i> Recent Transactions</span>
        <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="table-wrapper">
        <?php if (empty($recentRentals)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox d-block"></i>
            No transactions yet. <a href="rent.php">Create the first rental</a>.
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>Rent Date</th>
                    <th>Expected Return</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRentals as $r): ?>
                <tr>
                    <td><span class="rental-id">#<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                    <td><?= htmlspecialchars($r['customer_name']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td><?= (int)$r['quantity'] ?></td>
                    <td><?= htmlspecialchars($r['rent_date']) ?></td>
                    <td><?= htmlspecialchars($r['expected_return_date']) ?></td>
                    <td>
                        <?= $r['total_amount'] !== null
                            ? '&#8369;' . number_format((float)$r['total_amount'], 2)
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
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
