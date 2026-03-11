<?php
require 'db.php';

$page_title    = 'Return Equipment';
$page_subtitle = 'Process equipment returns';
$active_page   = 'return';

$success = '';
$error   = '';

// ── Process the return ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rentalId          = (int) trim($_POST['rental_id']          ?? '');
    $actualReturnDate  = trim($_POST['actual_return_date'] ?? '');

    if ($rentalId <= 0) {
        $error = 'Invalid rental record.';
    } elseif ($actualReturnDate === '') {
        $error = 'Actual return date is required.';
    } else {
        $pdo->beginTransaction();
        try {
            // Fetch rental (lock row)
            $stmt = $pdo->prepare("
                SELECT r.*, e.name AS equipment_name
                FROM rentals r
                JOIN equipment e ON r.equipment_id = e.id
                WHERE r.id = ? AND r.status = 'rented'
                FOR UPDATE
            ");
            $stmt->execute([$rentalId]);
            $rental = $stmt->fetch();

            if (!$rental) {
                throw new Exception('This rental has already been processed or does not exist.');
            }

            if ($actualReturnDate < $rental['rent_date']) {
                throw new Exception('Return date cannot be earlier than the rent date (' . $rental['rent_date'] . ').');
            }

            // Calculate days and total amount (minimum 1 day)
            $start     = new DateTime($rental['rent_date']);
            $end       = new DateTime($actualReturnDate);
            $totalDays = max(1, (int) $end->diff($start)->days);
            $totalAmt  = $totalDays * (float)$rental['daily_rate'] * (int)$rental['quantity'];

            // Update rental record
            $pdo->prepare("
                UPDATE rentals
                SET status              = 'returned',
                    actual_return_date  = ?,
                    total_days          = ?,
                    total_amount        = ?
                WHERE id = ?
            ")->execute([$actualReturnDate, $totalDays, $totalAmt, $rentalId]);

            // Restore available quantity
            $pdo->prepare("
                UPDATE equipment
                SET available_quantity = available_quantity + ?
                WHERE id = ?
            ")->execute([$rental['quantity'], $rental['equipment_id']]);

            $pdo->commit();

            $success = "Return processed for <strong>#" . str_pad($rentalId, 4, '0', STR_PAD_LEFT) .
                       "</strong> – <strong>" . htmlspecialchars($rental['customer_name']) . "</strong>." .
                       " Total: <strong>&#8369;" . number_format($totalAmt, 2) . "</strong>" .
                       " ($totalDays day" . ($totalDays > 1 ? 's' : '') . ").";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// ── Pre-selected rental from ?id= ────────────────────────────
$preSelected = null;
if (isset($_GET['id'])) {
    $preId = (int) $_GET['id'];
    $stmt  = $pdo->prepare("
        SELECT r.*, e.name AS equipment_name, e.daily_rate AS eq_daily_rate
        FROM rentals r
        JOIN equipment e ON r.equipment_id = e.id
        WHERE r.id = ? AND r.status = 'rented'
    ");
    $stmt->execute([$preId]);
    $preSelected = $stmt->fetch() ?: null;
}

// ── Fetch all active (rented) rentals ─────────────────────────
$activeRentals = $pdo->query("
    SELECT r.id, r.customer_name, r.customer_phone, r.quantity,
           r.rent_date, r.expected_return_date, r.daily_rate,
           e.name AS equipment_name,
           DATEDIFF(CURDATE(), r.rent_date) AS days_so_far,
           CASE WHEN r.expected_return_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue
    FROM rentals r
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.status = 'rented'
    ORDER BY r.expected_return_date ASC
")->fetchAll();

require 'header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-circle-check me-2"></i><?= $success ?>
    <a href="transactions.php" class="alert-link ms-2">View Transactions</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-circle-xmark me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- ── Return Form (shown when a rental is selected) ─────── -->
    <?php if ($preSelected): ?>
    <div class="col-12 col-lg-5">
        <div class="card border border-success" style="border-width:2px!important;">
            <div class="card-header" style="background:#f0fdf4;color:#14532d;">
                <i class="fas fa-rotate-left"></i>
                Process Return &nbsp;—&nbsp;
                <span class="rental-id">#<?= str_pad($preSelected['id'], 4, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div class="card-body p-4">
                <dl class="row mb-3" style="font-size:13.5px;">
                    <dt class="col-5 text-muted">Customer</dt>
                    <dd class="col-7"><?= htmlspecialchars($preSelected['customer_name']) ?></dd>
                    <dt class="col-5 text-muted">Equipment</dt>
                    <dd class="col-7"><?= htmlspecialchars($preSelected['equipment_name']) ?></dd>
                    <dt class="col-5 text-muted">Quantity</dt>
                    <dd class="col-7"><?= (int)$preSelected['quantity'] ?></dd>
                    <dt class="col-5 text-muted">Daily Rate</dt>
                    <dd class="col-7">&#8369;<?= number_format((float)$preSelected['daily_rate'], 2) ?></dd>
                    <dt class="col-5 text-muted">Rent Date</dt>
                    <dd class="col-7"><?= htmlspecialchars($preSelected['rent_date']) ?></dd>
                    <dt class="col-5 text-muted">Exp. Return</dt>
                    <dd class="col-7"><?= htmlspecialchars($preSelected['expected_return_date']) ?></dd>
                </dl>

                <hr class="section-hr">

                <form method="POST" action="return.php" id="returnForm">
                    <input type="hidden" name="rental_id" value="<?= (int)$preSelected['id'] ?>">

                    <div class="mb-4">
                        <label class="form-label">Actual Return Date <span class="text-danger">*</span></label>
                        <input type="date" name="actual_return_date" id="actualReturnDate"
                               class="form-control"
                               value="<?= date('Y-m-d') ?>"
                               min="<?= htmlspecialchars($preSelected['rent_date']) ?>"
                               required>
                    </div>

                    <!-- Live cost calculation -->
                    <div class="calc-box mb-4" id="returnCalcBox">
                        <div class="calc-row">
                            <span>Daily Rate × Qty</span>
                            <span id="rCalcRate">
                                &#8369;<?= number_format((float)$preSelected['daily_rate'] * (int)$preSelected['quantity'], 2) ?>
                            </span>
                        </div>
                        <div class="calc-row">
                            <span>Number of Days</span>
                            <span id="rCalcDays">—</span>
                        </div>
                        <div class="calc-row calc-total">
                            <span>Total Amount</span>
                            <span id="rCalcTotal">—</span>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-circle-check me-1"></i> Confirm Return
                        </button>
                        <a href="return.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function(){
        const rentDate = new Date('<?= $preSelected['rent_date'] ?>');
        const rateQty  = <?= (float)$preSelected['daily_rate'] * (int)$preSelected['quantity'] ?>;

        function calcReturn() {
            const returnVal = document.getElementById('actualReturnDate').value;
            if (!returnVal) return;
            const retDate = new Date(returnVal);
            let days = Math.round((retDate - rentDate) / 86400000);
            if (days < 1) days = 1;
            const total = days * rateQty;
            document.getElementById('rCalcDays').textContent  = days + ' day' + (days !== 1 ? 's' : '');
            document.getElementById('rCalcTotal').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
        }

        const input = document.getElementById('actualReturnDate');
        if (input) { input.addEventListener('change', calcReturn); calcReturn(); }
    })();
    </script>

    <div class="col-12 col-lg-7">
    <?php else: ?>
    <div class="col-12">
    <?php endif; ?>

        <!-- ── Active Rentals List ────────────────────────────── -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clock text-warning"></i> Active Rentals</span>
                <span class="badge bg-warning text-dark"><?= count($activeRentals) ?> pending</span>
            </div>
            <div class="table-wrapper">
                <?php if (empty($activeRentals)): ?>
                <div class="empty-state">
                    <i class="fas fa-circle-check d-block" style="color:#16a34a;opacity:.5;"></i>
                    No active rentals — everything has been returned!
                </div>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Equipment</th>
                            <th>Qty</th>
                            <th>Rent Date</th>
                            <th>Exp. Return</th>
                            <th>Days Out</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeRentals as $r): ?>
                        <tr <?= $r['is_overdue'] ? 'class="table-danger"' : '' ?>>
                            <td><span class="rental-id">#<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($r['customer_name']) ?></strong>
                                <?php if ($r['customer_phone']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($r['customer_phone']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                            <td><?= (int)$r['quantity'] ?></td>
                            <td><?= htmlspecialchars($r['rent_date']) ?></td>
                            <td class="<?= $r['is_overdue'] ? 'text-danger fw-semibold' : '' ?>">
                                <?= htmlspecialchars($r['expected_return_date']) ?>
                            </td>
                            <td>
                                <?php
                                $daysOut = max(0, (int)$r['days_so_far']);
                                echo $daysOut . ' day' . ($daysOut !== 1 ? 's' : '');
                                ?>
                                <?php if ($r['is_overdue']): ?>
                                <br><small class="text-danger fw-semibold">Overdue</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['is_overdue']): ?>
                                    <span class="badge-overdue">Overdue</span>
                                <?php else: ?>
                                    <span class="badge-rented">Rented</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="return.php?id=<?= (int)$r['id'] ?>"
                                   class="btn btn-sm btn-success">
                                    <i class="fas fa-rotate-left me-1"></i> Return
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
