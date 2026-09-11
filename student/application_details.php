<?php
// student/application_details.php
// View detailed information for a single application

$page_title = 'Application Details';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('student');

$conn = db_connect();
$student_id = current_user_id();
$application_id = $_GET['id'] ?? '';

// Validate application_id is numeric
if (!is_numeric($application_id)) {
    set_flash('error', 'Invalid application ID.');
    db_close($conn);
    redirect('student/my_applications.php');
}

// Fetch the application and verify it belongs to the logged-in student
$application = db_fetch_one($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at, a.updated_at, a.current_status_id, a.application_data,
           s.status_code, s.status_name, s.is_final,
           t.type_id, t.type_code, t.type_name, t.description as type_description, t.requires_payment, t.fee_amount
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    WHERE a.application_id = :app_id AND a.student_id = :sid
", ['app_id' => $application_id, 'sid' => $student_id]);

if (!$application) {
    set_flash('error', 'Application not found or you do not have permission to view it.');
    db_close($conn);
    redirect('student/my_applications.php');
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

// Convert CLOB comments in history
foreach ($status_history as &$h) {
    $h['COMMENTS'] = clob_to_string($h['COMMENTS']);
}

// Fetch payment info if payment is required for this type
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

// Fetch documents uploaded for this application
$documents = db_fetch_all($conn, "
    SELECT d.document_id, d.original_filename, d.file_path, d.file_size, d.mime_type,
           d.verification_status, d.uploaded_at
    FROM DOCUMENTS d
    WHERE d.application_id = :app_id
    ORDER BY d.uploaded_at DESC
", ['app_id' => $application_id]);

// Fetch reviews submitted by reviewers
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

// Fetch reviewer info
$reviewer = db_fetch_one($conn, "
    SELECT full_name FROM USERS WHERE user_id = (
        SELECT reviewer_id FROM APPLICATIONS WHERE application_id = :app_id
    )
", ['app_id' => $application_id]);
$reviewer_name = $reviewer ? $reviewer['FULL_NAME'] : null;

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

function format_file_size($bytes) {
    if (!$bytes) return 'N/A';
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
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

function rec_badge($rec) {
    $map = [
        'approve' => 'badge-success',
        'reject' => 'badge-danger',
        'request_info' => 'badge-warning'
    ];
    $labels = [
        'approve' => 'Recommended',
        'reject' => 'Not Recommended',
        'request_info' => 'Need More Information'
    ];
    $cls = $map[$rec] ?? 'badge-gray';
    $label = $labels[$rec] ?? ucfirst($rec);
    return '<span class="status-badge ' . $cls . '">' . $label . '</span>';
}
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Application Details</h1>
            <p>Reference: <?php echo e($application['REFERENCE_NUMBER']); ?></p>
        </div>

        <!-- Application Summary -->
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
            <div class="stat-card">
                <h3><?php echo e(format_date($application['SUBMITTED_AT'])); ?></h3>
                <p>Submitted</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="margin-bottom: 20px;">
            <a href="<?php echo base_url('student/my_applications.php'); ?>" class="btn btn-small">Back to Applications</a>
            <a href="<?php echo base_url('student/track_application.php?ref=' . $application['REFERENCE_NUMBER']); ?>" class="btn btn-small">Track This Application</a>
            <a href="<?php echo base_url('student/documents.php'); ?>" class="btn btn-small">View Documents</a>
            <a href="<?php echo base_url('student/payment_status.php'); ?>" class="btn btn-small">View Payments</a>
        </div>

        <!-- Application Data -->
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
                            <td>Fee Required</td>
                            <td><?php echo e($application['REQUIRES_PAYMENT'] === 'Y' ? 'Yes' : 'No'); ?></td>
                        </tr>
                        <tr>
                            <td>Fee Amount</td>
                            <td><?php echo e(number_format($application['FEE_AMOUNT'], 2) . ' BDT'); ?></td>
                        </tr>
                        <tr>
                            <td>Assigned Reviewer</td>
                            <td><?php echo e($reviewer_name ?? 'Not assigned yet'); ?></td>
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

        <!-- Payment Information -->
        <?php if ($application['REQUIRES_PAYMENT'] === 'Y'): ?>
            <div class="dashboard-section">
                <h2>Payment Information</h2>
                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <p>No payment record found yet. Payment is pending for this application.</p>
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
                                    <th>Date</th>
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
        <?php endif; ?>

        <!-- Submitted Documents -->
        <div class="dashboard-section">
            <h2>Submitted Documents</h2>
            <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <p>No documents have been submitted for this application.</p>
                    <a href="<?php echo base_url('student/documents.php'); ?>" class="btn btn-primary btn-small">Upload Documents</a>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><?php echo e($doc['ORIGINAL_FILENAME']); ?></td>
                                    <td><?php echo e(format_file_size($doc['FILE_SIZE'])); ?></td>
                                    <td><?php echo e($doc['MIME_TYPE']); ?></td>
                                    <td><?php echo doc_status_badge($doc['VERIFICATION_STATUS']); ?></td>
                                    <td><?php echo e(format_date($doc['UPLOADED_AT'])); ?></td>
                                    <td>
                                        <?php if (!empty($doc['FILE_PATH'])): ?>
                                            <a href="<?php echo base_url($doc['FILE_PATH']); ?>" target="_blank" class="btn btn-small">View</a>
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

        <!-- Reviewer Recommendations -->
        <div class="dashboard-section">
            <h2>Reviewer Recommendations</h2>
            <?php if (empty($reviews)): ?>
                <div class="empty-state">
                    <p>No review has been submitted for this application yet. You will be notified once a reviewer completes their evaluation.</p>
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
                                    <td><?php echo e($r['COMMENTS'] ?: 'No comments'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Status History -->
        <div class="dashboard-section">
            <h2>Status History</h2>
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
