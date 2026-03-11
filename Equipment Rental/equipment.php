<?php
require 'db.php';

$page_title    = 'Equipment';
$page_subtitle = 'Manage your rental inventory';
$active_page   = 'equipment';

$success = '';
$error   = '';

// ── Handle Add Equipment ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name      = trim($_POST['name']      ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $category  = trim($_POST['category']  ?? 'General');
    $rate      = (float) ($_POST['daily_rate'] ?? 0);
    $totalQty  = (int)   ($_POST['total_quantity'] ?? 1);

    if ($name === '') {
        $error = 'Equipment name is required.';
    } elseif ($rate <= 0) {
        $error = 'Daily rate must be greater than zero.';
    } elseif ($totalQty < 1) {
        $error = 'Quantity must be at least 1.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO equipment (name, description, category, daily_rate, total_quantity, available_quantity)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $desc, $category, $rate, $totalQty, $totalQty]);
        $success = "Equipment <strong>" . htmlspecialchars($name) . "</strong> added successfully.";
    }
}

// ── Handle Delete Equipment ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int) ($_POST['equipment_id'] ?? 0);
    if ($delId > 0) {
        // Only delete if no rentals exist for this equipment
        $check = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE equipment_id = ?");
        $check->execute([$delId]);
        if ((int)$check->fetchColumn() > 0) {
            $error = 'Cannot delete this equipment — it has existing rental records.';
        } else {
            $pdo->prepare("DELETE FROM equipment WHERE id = ?")->execute([$delId]);
            $success = 'Equipment deleted successfully.';
        }
    }
}

// ── Fetch all equipment ───────────────────────────────────────
$equipment = $pdo->query("SELECT * FROM equipment ORDER BY id DESC")->fetchAll();

require 'header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-circle-check me-2"></i><?= $success ?>
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
    <!-- ── Add Equipment Form ─────────────────────────────── -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-plus-circle text-primary"></i> Add New Equipment
            </div>
            <div class="card-body p-4">
                <form method="POST" action="equipment.php">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label">Equipment Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               placeholder="e.g. Concrete Mixer 350L"
                               maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="General">General</option>
                            <option value="Heavy Equipment">Heavy Equipment</option>
                            <option value="Mixing Equipment">Mixing Equipment</option>
                            <option value="Power Equipment">Power Equipment</option>
                            <option value="Construction">Construction</option>
                            <option value="Compaction">Compaction</option>
                            <option value="Breaking">Breaking</option>
                            <option value="Pumping">Pumping</option>
                            <option value="Lifting">Lifting</option>
                            <option value="Grinding">Grinding</option>
                            <option value="Pneumatic">Pneumatic</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Brief description"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Daily Rate (&#8369;) <span class="text-danger">*</span></label>
                        <input type="number" name="daily_rate" class="form-control"
                               step="0.01" min="0.01" placeholder="0.00" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Total Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="total_quantity" class="form-control"
                               min="1" value="1" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Add Equipment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Equipment List ────────────────────────────────────── -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list text-primary"></i> Equipment Inventory</span>
                <span class="badge bg-secondary"><?= count($equipment) ?> items</span>
            </div>
            <div class="table-wrapper">
                <?php if (empty($equipment)): ?>
                <div class="empty-state">
                    <i class="fas fa-toolbox d-block"></i>
                    No equipment found. Add your first item using the form.
                </div>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Daily Rate</th>
                            <th>Total</th>
                            <th>Available</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipment as $eq): ?>
                        <tr>
                            <td><?= (int)$eq['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($eq['name']) ?></strong>
                                <?php if ($eq['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars(mb_strimwidth($eq['description'], 0, 55, '…')) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border" style="font-size:11px;">
                                    <?= htmlspecialchars($eq['category']) ?>
                                </span>
                            </td>
                            <td>&#8369;<?= number_format((float)$eq['daily_rate'], 2) ?></td>
                            <td><?= (int)$eq['total_quantity'] ?></td>
                            <td><?= (int)$eq['available_quantity'] ?></td>
                            <td>
                                <?php if ((int)$eq['available_quantity'] > 0): ?>
                                    <span class="badge-available">Available</span>
                                <?php else: ?>
                                    <span class="badge-unavailable">Out</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="equipment.php"
                                      onsubmit="return confirm('Delete this equipment? This cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="equipment_id" value="<?= (int)$eq['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            title="Delete">
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                </form>
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
