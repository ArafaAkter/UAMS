<?php
// reviewer/application_details.php
// Detailed view of a single application for reviewers

$page_title = 'Application Details';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/header.php';

require_role('reviewer');

$conn = db_connect();
$reviewer_id = current_user_id();
$user = get_logged_in_user($conn);
$application_id = $_GET['id'] ?? '';

// Validate application_id
if (!is_numeric($application_id)) {
    set_flash('error', 'Invalid application ID.');
    db_close($conn);
    redirect('reviewer/applications.php');
}

// Fetch the application, verifying it is assigned to this reviewer
$application = db_fetch_one($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at, a.updated_at, a.current_status_id, a.application_data, a.reviewer_id,
           s.status_code, s.status_name, s.is_final,
           t.type_id, t.type_code, t.type_name, t.description as type_description, t.requires_payment, t.fee_amount,
           u.user_id as student_id, u.full_name as student_name, u.email as student_email, u.phone as student_phone, u.department as student_department
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    WHERE a.application_id = :app_id AND a.reviewer_id = :rid AND u.department = :reviewer_dept
", ['app_id' => $application_id, 'rid' => $reviewer_id, 'reviewer_dept' => $user['DEPARTMENT']]);

if (!$application) {
    set_flash('error', 'Application not found or not assigned to you.');
    db_close($conn);
    redirect('reviewer/applications.php');
}

// Decode application data JSON
$app_data = [];
if ($application['APPLICATION_DATA']) {
    $clob = $application['APPLICATION_DATA'];
    if (is_object($clob) && method_exists($clob, 'load')) {
        $clob = $clob->load();
    }
    $app_data = json_decode($clob, true) ?: [];
}

// Fetch status history
$status_history = db_fetch_all($conn, "
    SELECT h.history_id, h.status_id, h.comments, h.changed_at,
           s.status_name, s.status_code,
           u.full_name as changed_by_name
    FROM APPLICATION_STATUS_HISTORY h
    JOIN APPLICATION_STATUS s ON h.status_id = s.status_id
    LEFT JOIN USERS u ON h.changed_by = u.user_id
    WHERE h.application_id = :app_id
    ORDER BY h.changed_at DESC
", ['app_id' => $application_id]);

foreach ($status_history as &$h) {
    $h['COMMENTS'] = clob_to_string($h['COMMENTS']);
}

// Fetch documents uploaded for this application
$documents = db_fetch_all($conn, "
    SELECT d.document_id, d.original_filename, d.file_path, d.file_size, d.mime_type,
           d.verification_status, d.uploaded_at,
           u.full_name as uploaded_by_name
    FROM DOCUMENTS d
    JOIN USERS u ON d.uploaded_by = u.user_id
    WHERE d.application_id = :app_id
    ORDER BY d.uploaded_at DESC
", ['app_id' => $application_id]);

// Fetch payment info if payment is required
$payments = [];
if ($application['REQUIRES_PAYMENT'] === 'Y') {
    $payments = db_fetch_all($conn, "
        SELECT p.payment_id, p.amount, p.payment_method, p.transaction_ref, p.payment_date, p.status, p.receipt_path,
               v.full_name as verified_by_name
        FROM PAYMENTS p
        LEFT JOIN USERS v ON p.verified_by = v.user_id
        WHERE p.application_id = :app_id
        ORDER BY p.created_at DESC
    ", ['app_id' => $application_id]);
}

// Fetch existing reviews for this application
$reviews = db_fetch_all($conn, "
    SELECT r.review_id, r.recommendation, r.comments, r.review_date,
           u.full_name as reviewer_name
    FROM REVIEWS r
    JOIN USERS u ON r.reviewer_id = u.user_id
    WHERE r.application_id = :app_id
    ORDER BY r.review_date DESC
", ['app_id' => $application_id]);

foreach ($reviews as &$r) {
    $r['COMMENTS'] = clob_to_string($r['COMMENTS']);
}

// Handle reviewer status update
$errors = [];
 $allowed_status_ids = [3, 4, 7]; // under_review, reviewed, needs_review — no payment statuses

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reviewer_update_status'])) {
    validate_csrf();

    $new_status_id = trim($_POST['status_id'] ?? '');
    $reviewer_comments = trim($_POST['reviewer_comments'] ?? '');

    if (!is_numeric($new_status_id) || !in_array((int)$new_status_id, $allowed_status_ids)) {
        $errors['general'] = 'Invalid status selected.';
    } elseif (empty($reviewer_comments)) {
        $errors['reviewer_comments'] = 'Comments are required when updating status.';
    } else {
        $status = db_fetch_one($conn, "SELECT status_code, status_name FROM APPLICATION_STATUS WHERE status_id = :sid", ['sid' => $new_status_id]);
        if (!$status) {
            $errors['general'] = 'Invalid status selected.';
        } else {
            // Insert status history
            db_query($conn, "
                INSERT INTO APPLICATION_STATUS_HISTORY (application_id, status_id, changed_by, comments, changed_at)
                VALUES (:app_id, :sid, :rid, :comments, SYSDATE)
            ", [
                'app_id' => $application_id,
                'sid' => $new_status_id,
                'rid' => $reviewer_id,
                'comments' => $reviewer_comments
            ]);

            // Update application status
            db_query($conn, "
                UPDATE APPLICATIONS SET current_status_id = :sid, updated_at = SYSDATE
                WHERE application_id = :app_id
            ", ['sid' => $new_status_id, 'app_id' => $application_id]);

            set_flash('success', 'Application status updated successfully.');
            redirect('reviewer/application_details.php?id=' . $application_id);
        }
    }
}

db_close($conn);

function status_badge($code, $name) {
    $color_map = [
        'draft' => 'gray',
        'submitted' => 'info',
        'under_review' => 'warning',
        'reviewed' => 'info',
        'approved' => 'success',
        'rejected' => 'danger',
        'needs_review' => 'warning',
        'payment_pending' => 'warning',
        'completed' => 'success',
        'closed' => 'gray'
    ];
    $color = $color_map[$code] ?? 'gray';
    $badge_class = 'badge-' . $color;
    return '<span class="status-badge ' . $badge_class . '">' . e($name) . '</span>';
}

function doc_status_badge($status) {
    $map = [
        'pending' => 'badge-warning',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        'needs_correction' => 'badge-info'
    ];
    $labels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'needs_correction' => 'Needs Correction'
    ];
    $cls = $map[$status] ?? 'badge-gray';
    $label = $labels[$status] ?? ucfirst($status);
    return '<span class="status-badge ' . $cls . '">' . e($label) . '</span>';
}

function payment_status_badge($status) {
    $map = [
        'pending' => 'badge-warning',
        'verified' => 'badge-success',
        'failed' => 'badge-danger'
    ];
    $labels = [
        'pending' => 'Pending',
        'verified' => 'Paid',
        'failed' => 'Failed'
    ];
    $cls = $map[$status] ?? 'badge-gray';
    $label = $labels[$status] ?? ucfirst($status);
    return '<span class="status-badge ' . $cls . '">' . e($label) . '</span>';
}

function rec_badge($rec) {
    $map = [
        'approve' => 'badge-success',
        'reject' => 'badge-danger',
        'request_info' => 'badge-warning'
    ];
    $labels = [
        'approve' => 'Approved',
        'reject' => 'Rejected',
        'request_info' => 'Request Info'
    ];
    $cls = $map[$rec] ?? 'badge-gray';
    $label = $labels[$rec] ?? ucfirst($rec);
    return '<span class="status-badge ' . $cls . '">' . e($label) . '</span>';
}
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Application Details</h1>
            <p>Reference: <?php echo e($application['REFERENCE_NUMBER']); ?></p>
        </div>

        <!-- Flash Messages -->
        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo e($flash['type'] === 'error' ? 'error' : 'success'); ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($application['TYPE_NAME']); ?></h3>
                <p>Application Type</p>
            </div>
            <div class="stat-card">
                <h3><?php echo status_badge($application['STATUS_CODE'], $application['STATUS_NAME']); ?></h3>
                <p>Current Status</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($application['REQUIRES_PAYMENT'] === 'Y' ? number_format($application['FEE_AMOUNT'], 2) : '0.00'); ?> BDT</h3>
                <p>Fee Amount</p>
            </div>
            <div class="stat-card stat-card-highlight">
                <h3><?php echo e($application['STUDENT_NAME']); ?></h3>
                <p>Student</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="margin-bottom: 20px;">
            <a href="<?php echo base_url('reviewer/applications.php'); ?>" class="btn btn-small">Back to Applications</a>
            <a href="<?php echo base_url('reviewer/dashboard.php'); ?>" class="btn btn-small">Back to Dashboard</a>
            <?php if (empty($reviews)): ?>
                <a href="<?php echo base_url('reviewer/review_application.php?id=' . $application['APPLICATION_ID']); ?>" class="btn btn-small btn-primary">Start Review</a>
            <?php endif; ?>
        </div>

        <!-- Reviewer Status Update -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Update Application Status</h2>
            </div>

            <?php $flash = get_flash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo e($flash['type'] === 'error' ? 'error' : 'success'); ?>">
                    <?php echo e($flash['message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
            <?php endif; ?>

            <form method="POST" action="<?php echo base_url('reviewer/application_details.php?id=' . $application['APPLICATION_ID']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="reviewer_update_status" value="1">

                <div class="form-group">
                    <label for="status_id">New Status *</label>
                    <select id="status_id" name="status_id" required
                            class="<?php echo isset($errors['status_id']) ? 'input-error' : ''; ?>">
                        <option value="">-- Select status --</option>
                        <option value="3" <?php echo ($application['CURRENT_STATUS_ID'] == 3) ? 'selected' : ''; ?>>Under Review</option>
                        <option value="4" <?php echo ($application['CURRENT_STATUS_ID'] == 4) ? 'selected' : ''; ?>>Reviewed</option>
                        <option value="7" <?php echo ($application['CURRENT_STATUS_ID'] == 7) ? 'selected' : ''; ?>>Needs More Information</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reviewer_comments">Reviewer Comments *</label>
                    <textarea id="reviewer_comments" name="reviewer_comments" rows="3" required
                              class="<?php echo isset($errors['reviewer_comments']) ? 'input-error' : ''; ?>"
                              placeholder="Reason for status change..."><?php echo e($_POST['reviewer_comments'] ?? ''); ?></textarea>
                    <?php if (isset($errors['reviewer_comments'])): ?>
                        <span class="error-text"><?php echo e($errors['reviewer_comments']); ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">Update Status</button>
            </form>
        </div>

        <!-- Student Information -->
        <div class="dashboard-section">
            <h2>Student Information</h2>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Department</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo e($application['STUDENT_NAME']); ?></td>
                            <td><?php echo e($application['STUDENT_EMAIL']); ?></td>
                            <td><?php echo e($application['STUDENT_PHONE'] ?? 'N/A'); ?></td>
                            <td><?php echo e($application['STUDENT_DEPARTMENT'] ?? 'N/A'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Application Information -->
        <div class="dashboard-section">
            <h2>Application Information</h2>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Reference Number</td>
                            <td><?php echo e($application['REFERENCE_NUMBER']); ?></td>
                        </tr>
                        <tr>
                            <td>Application Type</td>
                            <td><?php echo e($application['TYPE_NAME']); ?></td>
                        </tr>
                        <tr>
                            <td>Type Description</td>
                            <td><?php echo e($application['TYPE_DESCRIPTION']); ?></td>
                        </tr>
                        <tr>
                            <td>Current Status</td>
                            <td><?php echo status_badge($application['STATUS_CODE'], $application['STATUS_NAME']); ?></td>
                        </tr>
                        <tr>
                            <td>Requires Payment</td>
                            <td><?php echo e($application['REQUIRES_PAYMENT'] === 'Y' ? 'Yes' : 'No'); ?></td>
                        </tr>
                        <tr>
                            <td>Fee Amount</td>
                            <td><?php echo e(number_format($application['FEE_AMOUNT'], 2) . ' BDT'); ?></td>
                        </tr>
                        <tr>
                            <td>Assigned Reviewer</td>
                            <td><?php echo e($user['FULL_NAME']); ?> (You)</td>
                        </tr>
                        <tr>
                            <td>Submitted At</td>
                            <td><?php echo e(format_date($application['SUBMITTED_AT'])); ?></td>
                        </tr>
                        <tr>
                            <td>Last Updated</td>
                            <td><?php echo e(format_date($application['UPDATED_AT'])); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Application Form Data -->
        <?php if (!empty($app_data)): ?>
            <div class="dashboard-section">
                <h2>Application Form Data</h2>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($app_data as $field_name => $field_value): ?>
                                <tr>
                                    <td><?php echo e(ucwords(str_replace('_', ' ', $field_name))); ?></td>
                                    <td><?php echo e($field_value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Submitted Documents -->
        <div class="dashboard-section">
            <h2>Submitted Documents</h2>
            <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <p>No documents have been submitted for this application.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Uploaded By</th>
                                <th>Size</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><a href="<?php echo base_url($doc['FILE_PATH']); ?>" target="_blank"><?php echo e($doc['ORIGINAL_FILENAME']); ?></a></td>
                                    <td><?php echo e($doc['UPLOADED_BY_NAME']); ?></td>
                                    <td><?php echo e(number_format($doc['FILE_SIZE'] / 1024, 2)); ?> KB</td>
                                    <td><?php echo e($doc['MIME_TYPE']); ?></td>
                                    <td><?php echo doc_status_badge($doc['VERIFICATION_STATUS']); ?></td>
                                    <td><?php echo e(format_date($doc['UPLOADED_AT'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Information -->
        <!-- <?php if ($application['REQUIRES_PAYMENT'] === 'Y'): ?>
            <div class="dashboard-section">
                <h2>Payment Status</h2>
                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <p>No payment record found. The student has not yet submitted a payment request.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Transaction Ref</th>
                                    <th>Status</th>
                                    <th>Verified By</th>
                                    <th>Payment Date</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td><?php echo e(number_format($pay['AMOUNT'], 2)); ?> BDT</td>
                                        <td><?php echo e($pay['PAYMENT_METHOD']); ?></td>
                                        <td><?php echo e($pay['TRANSACTION_REF'] ?? 'N/A'); ?></td>
                                        <td><?php echo payment_status_badge($pay['STATUS']); ?></td>
                                        <td><?php echo e($pay['VERIFIED_BY_NAME'] ?? 'Not verified'); ?></td>
                                        <td><?php echo e(format_date($pay['PAYMENT_DATE'])); ?></td>
                                        <td>
                                            <?php if (!empty($pay['RECEIPT_PATH'])): ?>
                                                <a href="<?php echo base_url($pay['RECEIPT_PATH']); ?>" target="_blank" class="btn btn-small">View Receipt</a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?> -->

        <!-- Reviews -->
        <div class="dashboard-section">
            <h2>Review History</h2>
            <?php if (empty($reviews)): ?>
                <div class="empty-state">
                    <p>No reviews have been submitted for this application yet.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reviewer</th>
                                <th>Recommendation</th>
                                <th>Date</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $r): ?>
                                <tr>
                                    <td><?php echo e($r['REVIEWER_NAME']); ?></td>
                                    <td><?php echo rec_badge($r['RECOMMENDATION']); ?></td>
                                    <td><?php echo e(format_date($r['REVIEW_DATE'])); ?></td>
                                    <td><?php echo e($r['COMMENTS']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Application Status History -->
        <div class="dashboard-section">
            <h2>Application Status History</h2>
            <?php if (empty($status_history)): ?>
                <p style="color: #666;">No status history available yet.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Changed By</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($status_history as $h): ?>
                                <tr>
                                    <td><?php echo e(format_date($h['CHANGED_AT'])); ?></td>
                                    <td><?php echo status_badge($h['STATUS_CODE'], $h['STATUS_NAME']); ?></td>
                                    <td><?php echo e($h['CHANGED_BY_NAME'] ?? 'System'); ?></td>
                                    <td><?php echo e($h['COMMENTS'] ?: 'No comments'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
