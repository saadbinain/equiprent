<?php
require 'db.php';

$page_title    = 'Rent Equipment';
$page_subtitle = 'Create a new rental transaction';
$active_page   = 'rent';

$success = '';
$error   = '';

// ── Fetch available equipment for the dropdown ─────────────────
$availableItems = $pdo->query("
    SELECT id, name, category, daily_rate, available_quantity
    FROM equipment
    WHERE available_quantity > 0
    ORDER BY name ASC
")->fetchAll();

// JSON-encode for client-side rate/qty lookup
$equipmentJson = json_encode(
    array_column($availableItems, null, 'id'),
    JSON_HEX_TAG | JSON_HEX_AMP
);

// ── Process rental form ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipmentId         = (int)   trim($_POST['equipment_id']         ?? '');
    $customerName        = trim($_POST['customer_name']        ?? '');
    $customerPhone       = trim($_POST['customer_phone']       ?? '');
    $customerAddress     = trim($_POST['customer_address']     ?? '');
    $quantity            = (int)   trim($_POST['quantity']             ?? '');
    $rentDate            = trim($_POST['rent_date']            ?? '');
    $expectedReturnDate  = trim($_POST['expected_return_date'] ?? '');
    $notes               = trim($_POST['notes']                ?? '');

    // ── Validation ──────────────────────────────────────────────
    if ($customerName === '') {
        $error = 'Customer name is required.';
    } elseif ($equipmentId <= 0) {
        $error = 'Please select equipment.';
    } elseif ($quantity < 1) {
        $error = 'Quantity must be at least 1.';
    } elseif ($rentDate === '' || $expectedReturnDate === '') {
        $error = 'Rent date and expected return date are required.';
    } elseif ($expectedReturnDate < $rentDate) {
        $error = 'Expected return date cannot be before the rent date.';
    } else {
        // Fetch current available quantity and daily rate (lock the row)
        $eqStmt = $pdo->prepare("SELECT name, daily_rate, available_quantity FROM equipment WHERE id = ? FOR UPDATE");
        $pdo->beginTransaction();
        $eqStmt->execute([$equipmentId]);
        $eq = $eqStmt->fetch();

        if (!$eq) {
            $pdo->rollBack();
            $error = 'Selected equipment not found.';
        } elseif ($quantity > (int)$eq['available_quantity']) {
            $pdo->rollBack();
            $error = 'Requested quantity exceeds available stock (' . (int)$eq['available_quantity'] . ' available).';
        } else {
            $dailyRate = (float) $eq['daily_rate'];

            // Insert rental
            $ins = $pdo->prepare("
                INSERT INTO rentals
                    (equipment_id, customer_name, customer_phone, customer_address,
                     quantity, daily_rate, rent_date, expected_return_date, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'rented')
            ");
            $ins->execute([
                $equipmentId, $customerName, $customerPhone, $customerAddress,
                $quantity, $dailyRate, $rentDate, $expectedReturnDate, $notes
            ]);
            $newRentalId = $pdo->lastInsertId();

            // Decrement available quantity
            $pdo->prepare("UPDATE equipment SET available_quantity = available_quantity - ? WHERE id = ?")
                ->execute([$quantity, $equipmentId]);

            $pdo->commit();

            $success = "Rental <strong>#" . str_pad($newRentalId, 4, '0', STR_PAD_LEFT) . "</strong> created successfully for <strong>" . htmlspecialchars($customerName) . "</strong>.";

            // Re-fetch available items list after update
            $availableItems = $pdo->query("
                SELECT id, name, category, daily_rate, available_quantity
                FROM equipment WHERE available_quantity > 0 ORDER BY name ASC
            ")->fetchAll();
            $equipmentJson = json_encode(array_column($availableItems, null, 'id'), JSON_HEX_TAG | JSON_HEX_AMP);
        }
    }
}

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
    <!-- ── Rental Form ───────────────────────────────────────── -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-hand-holding text-primary"></i> Rental Details
            </div>
            <div class="card-body p-4">
                <?php if (empty($availableItems)): ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-triangle-exclamation me-2"></i>
                    No equipment is currently available for rent.
                    <a href="equipment.php" class="alert-link">Return equipment</a> or
                    <a href="equipment.php" class="alert-link">add new equipment</a>.
                </div>
                <?php else: ?>
                <form method="POST" action="rent.php" id="rentForm">

                    <h6 class="fw-semibold text-muted mb-3" style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;">
                        Customer Information
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control"
                                   placeholder="Full name" maxlength="100" required
                                   value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="customer_phone" class="form-control"
                                   placeholder="e.g. 09XX-XXX-XXXX" maxlength="20"
                                   value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="customer_address" class="form-control"
                               placeholder="Street, City, Province"
                               value="<?= htmlspecialchars($_POST['customer_address'] ?? '') ?>">
                    </div>

                    <hr class="section-hr">

                    <h6 class="fw-semibold text-muted mb-3" style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;">
                        Equipment & Schedule
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-8">
                            <label class="form-label">Equipment <span class="text-danger">*</span></label>
                            <select name="equipment_id" id="equipmentSelect" class="form-select" required>
                                <option value="">— Select Equipment —</option>
                                <?php foreach ($availableItems as $eq): ?>
                                <option value="<?= (int)$eq['id'] ?>"
                                    <?= (isset($_POST['equipment_id']) && (int)$_POST['equipment_id'] === (int)$eq['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($eq['name']) ?>
                                    (&#8369;<?= number_format((float)$eq['daily_rate'], 2) ?>/day
                                     · <?= (int)$eq['available_quantity'] ?> avail.)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="quantityInput"
                                   class="form-control" min="1" value="<?= (int)($_POST['quantity'] ?? 1) ?>" required>
                            <div id="qtyHelp" class="form-text"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Rent Date <span class="text-danger">*</span></label>
                            <input type="date" name="rent_date" id="rentDate" class="form-control"
                                   value="<?= htmlspecialchars($_POST['rent_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Expected Return Date <span class="text-danger">*</span></label>
                            <input type="date" name="expected_return_date" id="returnDate"
                                   class="form-control"
                                   value="<?= htmlspecialchars($_POST['expected_return_date'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Optional notes or remarks"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-circle-check me-1"></i> Confirm Rental
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Cost Estimate Panel ───────────────────────────────── -->
    <div class="col-12 col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-calculator text-primary"></i> Cost Estimate
            </div>
            <div class="card-body p-4">
                <div class="calc-box" id="calcBox">
                    <div class="calc-row">
                        <span>Daily Rate</span>
                        <span id="calcRate">&#8369;0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>Quantity</span>
                        <span id="calcQty">1</span>
                    </div>
                    <div class="calc-row">
                        <span>Number of Days</span>
                        <span id="calcDays">0</span>
                    </div>
                    <div class="calc-row calc-total">
                        <span>Estimated Total</span>
                        <span id="calcTotal">&#8369;0.00</span>
                    </div>
                </div>
                <p class="text-muted mt-3 mb-0" style="font-size:12px;">
                    <i class="fas fa-circle-info me-1"></i>
                    Actual total is computed upon return based on the real number of rental days.
                </p>
            </div>
        </div>

        <!-- Available Equipment Summary -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-boxes-stacked text-primary"></i> Available Stock
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" style="font-size:13px;">
                    <?php foreach (array_slice($availableItems, 0, 8) as $eq): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-2">
                        <span><?= htmlspecialchars($eq['name']) ?></span>
                        <span class="badge-available"><?= (int)$eq['available_quantity'] ?> avail.</span>
                    </li>
                    <?php endforeach; ?>
                    <?php if (count($availableItems) > 8): ?>
                    <li class="list-group-item text-center text-muted py-2" style="font-size:12px;">
                        + <?= count($availableItems) - 8 ?> more items
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const equipmentData = <?= $equipmentJson ?>;

function updateCalc() {
    const eqId  = document.getElementById('equipmentSelect')?.value;
    const qty   = parseInt(document.getElementById('quantityInput')?.value) || 0;
    const d1    = document.getElementById('rentDate')?.value;
    const d2    = document.getElementById('returnDate')?.value;

    let rate = 0, maxQty = 0;
    if (eqId && equipmentData[eqId]) {
        rate   = parseFloat(equipmentData[eqId].daily_rate);
        maxQty = parseInt(equipmentData[eqId].available_quantity);
        document.getElementById('qtyHelp').textContent = 'Max available: ' + maxQty;
        document.getElementById('quantityInput').max = maxQty;
    }

    let days = 0;
    if (d1 && d2 && d2 >= d1) {
        const diff = new Date(d2) - new Date(d1);
        days = Math.round(diff / (1000 * 60 * 60 * 24));
    }

    const total = rate * qty * days;

    document.getElementById('calcRate').textContent  = '₱' + rate.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('calcQty').textContent   = qty;
    document.getElementById('calcDays').textContent  = days;
    document.getElementById('calcTotal').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
}

['equipmentSelect','quantityInput','rentDate','returnDate'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', updateCalc);
    if (el) el.addEventListener('input',  updateCalc);
});

// Set default return date to tomorrow
const returnDate = document.getElementById('returnDate');
if (returnDate && !returnDate.value) {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    returnDate.value = tomorrow.toISOString().split('T')[0];
}

updateCalc();
</script>

<?php require 'footer.php'; ?>
