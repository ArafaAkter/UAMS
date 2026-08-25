<?php
// reviewer/review_application.php
// Form for submitting a review on an assigned application

$page_title = 'Review Application';
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

// Fetch application and verify it's assigned to this reviewer
$application = db_fetch_one($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at, a.current_status_id,
           s.status_code, s.status_name,
           t.type_name, t.fee_amount, t.requires_payment,
           u.full_name as student_name, u.email as student_email, u.department as student_department
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    WHERE a.application_id = :app_id AND a.reviewer_id = :rid
", ['app_id' => $application_id, 'rid' => $reviewer_id]);

if (!$application) {
    set_flash('error', 'Application not found or not assigned to you.');
    db_close($conn);
    redirect('reviewer/applications.php');
}

// Check if this reviewer has already reviewed this application
$existing_review = db_fetch_one($conn, "
    SELECT review_id FROM REVIEWS
    WHERE application_id = :app_id AND reviewer_id = :rid
", ['app_id' => $application_id, 'rid' => $reviewer_id]);

if ($existing_review) {
    set_flash('error', 'You have already reviewed this application.');
    db_close($conn);
    redirect('reviewer/application_details.php?id=' . $application_id);
}

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $recommendation = trim($_POST['recommendation'] ?? '');
    $comments = trim($_POST['comments'] ?? '');

    $allowed_recs = ['approve', 'reject', 'request_info'];

    if (!in_array($recommendation, $allowed_recs)) {
        $errors['recommendation'] = 'Please select a valid recommendation.';
    }

    if (empty($comments)) {
        $errors['comments'] = 'Comments are required.';
    } elseif (strlen($comments) > 4000) {
        $errors['comments'] = 'Comments are too long (max 4000 characters).';
    }

    if (empty($errors)) {
        // Map recommendation to new application status
        // Reviewers do NOT make final approval/rejection decisions
        // - approve → status 4 (reviewed) - awaiting admin decision
        // - reject → status 4 (reviewed) - reviewer recommends, admin decides
        // - request_info → status 7 (needs_review) - student needs to provide more info
        $status_map = [
            'approve' => 4,      // reviewed
            'reject' => 4,       // reviewed (admin makes final rejection)
            'request_info' => 7  // needs_review
        ];
        $new_status_id = $status_map[$recommendation];

        // Map recommendation to human-readable labels
        $rec_labels = [
            'approve' => 'Recommended',
            'reject' => 'Not Recommended',
            'request_info' => 'Need More Information'
        ];
        $rec_label = $rec_labels[$recommendation];

        // 1. Insert REVIEWS record
        $review_stid = db_query($conn, "
            INSERT INTO REVIEWS (application_id, reviewer_id, recommendation, comments, review_date, created_at, updated_at)
            VALUES (:app_id, :rid, :rec, :comments, SYSDATE, SYSDATE, SYSDATE)
        ", [
            'app_id' => $application_id,
            'rid' => $reviewer_id,
            'rec' => $recommendation,
            'comments' => $comments
        ]);

        // 2. Update application status
        db_query($conn, "
            UPDATE APPLICATIONS
            SET current_status_id = :status_id, updated_at = SYSDATE
            WHERE application_id = :app_id
        ", [
            'status_id' => $new_status_id,
            'app_id' => $application_id
        ]);

        // 3. Insert APPLICATION_STATUS_HISTORY
        $history_comments = 'Review submitted: ' . $rec_label . '. Comments: ' . $comments;
        db_query($conn, "
            INSERT INTO APPLICATION_STATUS_HISTORY (application_id, status_id, changed_by, comments, changed_at)
            VALUES (:app_id, :status_id, :rid, :comments, SYSDATE)
        ", [
            'app_id' => $application_id,
            'status_id' => $new_status_id,
            'rid' => $reviewer_id,
            'comments' => $history_comments
        ]);

        $success = true;
    }
}

db_close($conn);

// Status badge helper (local definition matching project convention)
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
            <h1>Review Application</h1>
            <p>Reference: <?php echo e($application['REFERENCE_NUMBER']); ?></p>
        </div>

        <!-- Success/Error -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                Review submitted successfully! The application status has been updated.
                <a href="<?php echo base_url('reviewer/application_details.php?id=' . $application['APPLICATION_ID']); ?>" class="btn btn-small">View Application Details</a>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
        <?php endif; ?>

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
                <h3><?php echo e($application['STUDENT_NAME']); ?></h3>
                <p>Student</p>
            </div>
            <div class="stat-card stat-card-highlight">
                <h3><?php echo e($application['FEE_AMOUNT'] > 0 ? number_format($application['FEE_AMOUNT'], 2) . ' BDT' : 'No Fee'); ?></h3>
                <p>Application Fee</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="margin-bottom: 20px;">
            <a href="<?php echo base_url('reviewer/application_details.php?id=' . $application['APPLICATION_ID']); ?>" class="btn btn-small">Back to Application Details</a>
            <a href="<?php echo base_url('reviewer/applications.php'); ?>" class="btn btn-small">Back to Applications</a>
        </div>

        <!-- Review Form -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Submit Review</h2>
                <p>Recommendation for: <?php echo e($application['TYPE_NAME'] . ' (' . $application['REFERENCE_NUMBER'] . ')'); ?></p>
            </div>

            <form method="POST" action="<?php echo base_url('reviewer/review_application.php?id=' . $application['APPLICATION_ID']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <div class="form-group">
                    <label for="recommendation">Recommendation *</label>
                    <select id="recommendation" name="recommendation" required
                            class="<?php echo isset($errors['recommendation']) ? 'input-error' : ''; ?>">
                        <option value="">-- Select a recommendation --</option>
                        <option value="approve" <?php echo (($_POST['recommendation'] ?? '') === 'approve') ? 'selected' : ''; ?>>Recommended</option>
                        <option value="reject" <?php echo (($_POST['recommendation'] ?? '') === 'reject') ? 'selected' : ''; ?>>Not Recommended</option>
                        <option value="request_info" <?php echo (($_POST['recommendation'] ?? '') === 'request_info') ? 'selected' : ''; ?>>Need More Information</option>
                    </select>
                    <?php if (isset($errors['recommendation'])): ?>
                        <span class="error-text"><?php echo e($errors['recommendation']); ?></span>
                    <?php endif; ?>
                    <p style="color: #666; font-size: 0.85rem; margin-top: 5px;">
                        Note: Reviewers provide recommendations. Final approval/rejection decisions
                        are made by administrators.
                    </p>
                </div>

                <div class="form-group">
                    <label for="comments">Review Comments *</label>
                    <textarea id="comments" name="comments" rows="6" required
                              class="<?php echo isset($errors['comments']) ? 'input-error' : ''; ?>"
                              placeholder="Provide detailed feedback on the application..."
                              maxlength="4000"><?php echo e($_POST['comments'] ?? ''); ?></textarea>
                    <?php if (isset($errors['comments'])): ?>
                        <span class="error-text"><?php echo e($errors['comments']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label style="font-weight: bold;">Before submitting, ensure you have:</label>
                    <ul style="margin: 5px 0; padding-left: 20px; color: #555;">
                        <li>Reviewed the student's <a href="<?php echo base_url('reviewer/application_details.php?id=' . $application['APPLICATION_ID'] . '#documents'); ?>" class="btn btn-small">submitted documents</a></li>
                        <li>Checked the <a href="<?php echo base_url('reviewer/application_details.php?id=' . $application['APPLICATION_ID'] . '#payment'); ?>" class="btn btn-small">payment status</a></li>
                        <li>Reviewed the <a href="<?php echo base_url('reviewer/application_details.php?id=' . $application['APPLICATION_ID'] . '#form-data'); ?>" class="btn btn-small">application form data</a></li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-primary">Submit Review</button>
            </form>
        </div>

        <!-- Existing Reviews -->
        <?php
        $conn = db_connect();
        $existing_reviews = db_fetch_all($conn, "
            SELECT r.review_id, r.recommendation, r.comments, r.review_date,
                   u.full_name as reviewer_name
            FROM REVIEWS r
            JOIN USERS u ON r.reviewer_id = u.user_id
            WHERE r.application_id = :app_id
            ORDER BY r.review_date DESC
        ", ['app_id' => $application_id]);
        db_close($conn);
        ?>
        <?php if (!empty($existing_reviews)): ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Existing Reviews</h2>
                </div>
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
                            <?php foreach ($existing_reviews as $r): ?>
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
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
