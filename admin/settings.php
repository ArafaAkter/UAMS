<?php
// admin/settings.php
// Manage application types (CRUD)

$page_title = 'Application Types';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/header.php';

require_role('admin');

$conn = db_connect();
$admin_id = current_user_id();

$errors = [];
$success = false;
$edit_mode = false;
$edit_type = null;

// Handle save (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_type'])) {
    validate_csrf();

    $type_id = trim($_POST['type_id'] ?? '');
    $type_code = trim($_POST['type_code'] ?? '');
    $type_name = trim($_POST['type_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requires_payment = trim($_POST['requires_payment'] ?? 'N');
    $fee_amount = trim($_POST['fee_amount'] ?? '0');
    $is_active = trim($_POST['is_active'] ?? 'Y');

    if (empty($type_code) || empty($type_name)) {
        $errors['general'] = 'Type code and name are required.';
    } elseif (!in_array($requires_payment, ['Y', 'N'])) {
        $errors['general'] = 'Invalid payment requirement.';
    } elseif (!is_numeric($fee_amount) || $fee_amount < 0) {
        $errors['general'] = 'Invalid fee amount.';
    } elseif (!in_array($is_active, ['Y', 'N'])) {
        $errors['general'] = 'Invalid active status.';
    } else {
        if (empty($type_id)) {
            // Check uniqueness
            $existing = db_fetch_one($conn, "SELECT type_id FROM APPLICATION_TYPES WHERE type_code = :code OR type_name = :name", ['code' => $type_code, 'name' => $type_name]);
            if ($existing) {
                $errors['general'] = 'Type code or name already exists.';
            } else {
                $stid = db_query($conn, "
                    INSERT INTO APPLICATION_TYPES (type_code, type_name, description, requires_payment, fee_amount, is_active)
                    VALUES (:code, :name, :descr, :req, :fee, :active)
                ", [
                    'code' => $type_code,
                    'name' => $type_name,
                    'descr' => $description ?: null,
                    'req' => $requires_payment,
                    'fee' => $fee_amount,
                    'active' => $is_active
                ]);
                $success = db_affected_rows($conn, $stid) > 0;
            }
        } else {
                $stid = db_query($conn, "
                    UPDATE APPLICATION_TYPES
                    SET type_code = :code, type_name = :name, description = :descr,
                        requires_payment = :req, fee_amount = :fee, is_active = :active
                    WHERE type_id = :tid
                ", [
                    'code' => $type_code,
                    'name' => $type_name,
                    'descr' => $description ?: null,
                    'req' => $requires_payment,
                    'fee' => $fee_amount,
                    'active' => $is_active,
                    'tid' => $type_id
                ]);
            $success = db_affected_rows($conn, $stid) > 0;
        }
    }
}

// Handle add request
if (isset($_GET['add'])) {
    $edit_mode = true;
    $edit_type = [
        'TYPE_ID' => '',
        'TYPE_CODE' => '',
        'TYPE_NAME' => '',
        'DESCRIPTION' => '',
        'REQUIRES_PAYMENT' => 'N',
        'FEE_AMOUNT' => '0',
        'IS_ACTIVE' => 'Y'
    ];
}

// Handle edit request
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_type = db_fetch_one($conn, "
        SELECT type_id, type_code, type_name, description, requires_payment, fee_amount, is_active
        FROM APPLICATION_TYPES WHERE type_id = :tid
    ", ['tid' => $_GET['edit']]);
    if ($edit_type) {
        $edit_mode = true;
    }
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $target_id = $_GET['delete'];
    // Check if type is in use
    $in_use = db_fetch_value($conn, "SELECT COUNT(*) FROM APPLICATIONS WHERE type_id = :tid", ['tid' => $target_id]);
    if ($in_use == 0) {
        db_query($conn, "DELETE FROM APPLICATION_TYPES WHERE type_id = :tid", ['tid' => $target_id]);
        $success = true;
    } else {
        $errors['general'] = 'Cannot delete: this type is used by existing applications.';
    }
}

// Fetch all types
$app_types = db_fetch_all($conn, "
    SELECT t.type_id, t.type_code, t.type_name, t.description, t.requires_payment, t.fee_amount, t.is_active,
           COUNT(a.application_id) as app_count
    FROM APPLICATION_TYPES t
    LEFT JOIN APPLICATIONS a ON t.type_id = a.type_id
    GROUP BY t.type_id, t.type_code, t.type_name, t.description, t.requires_payment, t.fee_amount, t.is_active
    ORDER BY t.type_id
");

db_close($conn);
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Application Types</h1>
            <p>Manage application types and fees</p>
        </div>

        <?php if ($success && !$edit_mode): ?>
            <div class="alert alert-success">Operation completed successfully.</div>
        <?php endif; ?>
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
        <?php endif; ?>

        <!-- Add/Edit Form -->
        <?php if ($edit_mode): ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h2><?php echo isset($edit_type['TYPE_ID']) ? 'Edit Application Type' : 'Add New Application Type'; ?></h2>
                    <a href="<?php echo base_url('admin/settings.php'); ?>" class="btn btn-small">Cancel</a>
                </div>

                <form method="POST" action="<?php echo base_url('admin/settings.php'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="save_type" value="1">
                    <?php if (isset($edit_type['TYPE_ID'])): ?>
                        <input type="hidden" name="type_id" value="<?php echo e($edit_type['TYPE_ID']); ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="type_code">Type Code *</label>
                        <input type="text" id="type_code" name="type_code" required
                               value="<?php echo e($edit_type['TYPE_CODE'] ?? ''); ?>"
                               placeholder="e.g., admission, transcript"
                               class="<?php echo isset($errors['type_code']) ? 'input-error' : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="type_name">Type Name *</label>
                        <input type="text" id="type_name" name="type_name" required
                               value="<?php echo e($edit_type['TYPE_NAME'] ?? ''); ?>"
                               placeholder="e.g., Admission Request"
                               class="<?php echo isset($errors['type_name']) ? 'input-error' : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="2"
                                  placeholder="Brief description..."><?php echo e($edit_type['DESCRIPTION'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="requires_payment">Requires Payment</label>
                        <select id="requires_payment" name="requires_payment">
                            <option value="N" <?php echo (($edit_type['REQUIRES_PAYMENT'] ?? 'N') === 'N') ? 'selected' : ''; ?>>No</option>
                            <option value="Y" <?php echo (($edit_type['REQUIRES_PAYMENT'] ?? '') === 'Y') ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fee_amount">Fee Amount (BDT)</label>
                        <input type="number" id="fee_amount" name="fee_amount" step="0.01" min="0"
                               value="<?php echo e($edit_type['FEE_AMOUNT'] ?? '0'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="is_active">Status</label>
                        <select id="is_active" name="is_active">
                            <option value="Y" <?php echo (($edit_type['IS_ACTIVE'] ?? 'Y') === 'Y') ? 'selected' : ''; ?>>Active</option>
                            <option value="N" <?php echo (($edit_type['IS_ACTIVE'] ?? '') === 'N') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Application Type</button>
                    <a href="<?php echo base_url('admin/settings.php'); ?>" class="btn">Cancel</a>
                </form>
            </div>
        <?php endif; ?>

        <!-- Application Types List -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>All Application Types</h2>
                <a href="<?php echo base_url('admin/settings.php?add=1'); ?>" class="btn btn-small btn-primary">Add New Type</a>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Requires Payment</th>
                            <th>Fee</th>
                            <th>Applications</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($app_types as $t): ?>
                            <tr>
                                <td><?php echo e($t['TYPE_CODE']); ?></td>
                                <td><?php echo e($t['TYPE_NAME']); ?></td>
                                <td><?php echo e($t['DESCRIPTION'] ?? 'N/A'); ?></td>
                                <td><?php echo e($t['REQUIRES_PAYMENT'] === 'Y' ? 'Yes' : 'No'); ?></td>
                                <td><?php echo e(number_format($t['FEE_AMOUNT'], 2)); ?> BDT</td>
                                <td><?php echo e($t['APP_COUNT']); ?></td>
                                <td>
                                    <?php if ($t['IS_ACTIVE'] === 'Y'): ?>
                                        <span class="status-badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo base_url('admin/settings.php?edit=' . $t['TYPE_ID']); ?>" class="btn btn-small">Edit</a>
                                    <?php if ($t['APP_COUNT'] == 0): ?>
                                        <a href="<?php echo base_url('admin/settings.php?delete=' . $t['TYPE_ID']); ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
