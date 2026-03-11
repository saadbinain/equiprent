<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Equipment Rental System') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">

    <!-- ── Sidebar ──────────────────────────────────────────── -->
    <nav class="sidebar">
        <div class="sidebar-brand d-flex align-items-center gap-3">
            <div class="brand-icon"><i class="fas fa-wrench"></i></div>
            <div>
                <p class="brand-title">EquipRent</p>
                <p class="brand-sub">Management System</p>
            </div>
        </div>

        <div class="sidebar-nav">
            <p class="nav-section-label">Main Menu</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= ($active_page ?? '') === 'dashboard' ? 'active' : '' ?>"
                       href="index.php">
                        <i class="fas fa-gauge-high"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_page ?? '') === 'equipment' ? 'active' : '' ?>"
                       href="equipment.php">
                        <i class="fas fa-toolbox"></i> Equipment
                    </a>
                </li>
            </ul>

            <p class="nav-section-label mt-2">Transactions</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= ($active_page ?? '') === 'rent' ? 'active' : '' ?>"
                       href="rent.php">
                        <i class="fas fa-hand-holding"></i> Rent Equipment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_page ?? '') === 'transactions' ? 'active' : '' ?>"
                       href="transactions.php">
                        <i class="fas fa-list-check"></i> Transactions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_page ?? '') === 'return' ? 'active' : '' ?>"
                       href="return.php">
                        <i class="fas fa-rotate-left"></i> Return Equipment
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer">
            <i class="fas fa-calendar-days me-1"></i>
            <?= date('F d, Y') ?>
        </div>
    </nav>

    <!-- ── Main Content ─────────────────────────────────────── -->
    <div class="main-content">
        <div class="top-bar">
            <div>
                <p class="page-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></p>
                <?php if (!empty($page_subtitle)): ?>
                <p class="page-subtitle"><?= htmlspecialchars($page_subtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success" style="font-size:11px;">
                    <i class="fas fa-circle me-1" style="font-size:8px;"></i>System Online
                </span>
                <span class="text-muted" style="font-size:12px;">
                    <i class="fas fa-database me-1"></i>comshop
                </span>
            </div>
        </div>
        <div class="content">
