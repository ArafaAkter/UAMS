<?php
// student/apply.php
// Application type selection + form + submission

$page_title = 'Apply New Application';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/header.php';

require_role('student');

$conn = db_connect();
$user = get_logged_in_user($conn);
$student_id = current_user_id();

// Status ID for 'submitted' - looked up from the database
$submitted_status = db_fetch_one($conn, "SELECT status_id FROM APPLICATION_STATUS WHERE status_code = 'submitted'");
$submitted_status_id = $submitted_status['STATUS_ID'];

// Form field definitions per application type
// Maps type_code to form fields
$type_fields = [
    'admission' => [
        ['name' => 'gpa', 'label' => 'GPA', 'type' => 'number', 'step' => '0.01', 'required' => true, 'min' => '0', 'max' => '4'],
        ['name' => 'previous_university', 'label' => 'Previous University', 'type' => 'text', 'required' => true],
        ['name' => 'program', 'label' => 'Program', 'type' => 'text', 'required' => true],
        ['name' => 'motivation', 'label' => 'Motivation Letter', 'type' => 'textarea', 'required' => true, 'rows' => 4],
    ],
    'scholarship' => [
        ['name' => 'gpa', 'label' => 'GPA', 'type' => 'number', 'step' => '0.01', 'required' => true, 'min' => '0', 'max' => '4'],
        ['name' => 'previous_university', 'label' => 'Previous University', 'type' => 'text', 'required' => true],
        ['name' => 'program', 'label' => 'Program', 'type' => 'text', 'required' => true],
        ['name' => 'achievements', 'label' => 'Achievements', 'type' => 'textarea', 'required' => false, 'rows' => 4],
    ],
    'transcript' => [
        ['name' => 'purpose', 'label' => 'Purpose', 'type' => 'text', 'required' => true],
        ['name' => 'destination', 'label' => 'Destination Country', 'type' => 'text', 'required' => true],
        ['name' => 'university', 'label' => 'University/Institution', 'type' => 'text', 'required' => true],
    ],
    'certificate' => [
        ['name' => 'certificate_type', 'label' => 'Certificate Type', 'type' => 'text', 'required' => true],
        ['name' => 'purpose', 'label' => 'Purpose', 'type' => 'textarea', 'required' => true, 'rows' => 3],
    ],
    'id_card' => [
        ['name' => 'card_type', 'label' => 'Card Type', 'type' => 'select', 'options' => ['new' => 'New', 'renewal' => 'Renewal', 'replacement' => 'Replacement'], 'required' => true],
        ['name' => 'reason', 'label' => 'Reason (if replacement)', 'type' => 'textarea', 'required' => false, 'rows' => 3],
        ['name' => 'address', 'label' => 'Current Address', 'type' => 'textarea', 'required' => true, 'rows' => 3],
    ],
    'dept_course_change' => [
        ['name' => 'current_department', 'label' => 'Current Department', 'type' => 'text', 'required' => true],
        ['name' => 'new_department', 'label' => 'New Department', 'type' => 'text', 'required' => true],
        ['name' => 'reason', 'label' => 'Reason for Change', 'type' => 'textarea', 'required' => true, 'rows' => 4],
    ],
];

$errors = [];
$success = false;

// Fetch all active application types
$app_types = db_fetch_all($conn, "
    SELECT type_id, type_code, type_name, description, fee_amount, requires_payment
    FROM APPLICATION_TYPES
    WHERE is_active = 'Y'
    ORDER BY type_name
");

// Build a lookup: type_code -> type info
$type_lookup = [];
foreach ($app_types as $t) {
    $type_lookup[$t['TYPE_CODE']] = $t;
}

// Determine selected type
$selected_type_id = $_GET['type'] ?? $_POST['type_id'] ?? '';
$selected_type = null;
foreach ($app_types as $t) {
    if ((string)$t['TYPE_ID'] === (string)$selected_type_id) {
        $selected_type = $t;
        break;
    }
}

// Get form fields for the selected type
$fields = [];
if ($selected_type && isset($type_fields[$selected_type['TYPE_CODE']])) {
    $fields = $type_fields[$selected_type['TYPE_CODE']];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_type) {
    validate_csrf();

    $type_code = $selected_type['TYPE_CODE'];

    // Validate and collect form data
    $application_data = [];
    foreach ($fields as $field) {
        $value = trim($_POST[$field['name']] ?? '');
        if ($field['required'] && $value === '') {
            $errors[$field['name']] = $field['label'] . ' is required.';
        }
        if ($field['type'] === 'number' && $value !== '') {
            if (!is_numeric($value)) {
                $errors[$field['name']] = $field['label'] . ' must be a valid number.';
            }
            if (isset($field['min']) && $value < $field['min']) {
                $errors[$field['name']] = $field['LABEL'] . ' must be at least ' . $field['min'] . '.';
            }
            if (isset($field['max']) && $value > $field['max']) {
                $errors[$field['name']] = $field['label'] . ' must not exceed ' . $field['max'] . '.';
            }
        }
        $application_data[$field['name']] = $value;
    }

    if (empty($errors)) {
        // Generate reference number: UAMS-{year}-{sequence}
        $year = date('Y');
        $pattern = 'UAMS-' . $year . '-%';
        $seq = db_fetch_value($conn, "SELECT COUNT(*) + 1 FROM APPLICATIONS WHERE reference_number LIKE :pattern", ['pattern' => $pattern]);
        $reference_number = 'UAMS-' . $year . '-' . sprintf('%04d', $seq);

        // Insert the application
        $json_data = json_encode($application_data, JSON_UNESCAPED_UNICODE);
        $sql = "INSERT INTO APPLICATIONS (student_id, type_id, reference_number, current_status_id, application_data, submitted_at)
                VALUES (:sid, :type_id, :ref, :status_id, TO_CLOB(:data), SYSDATE)";
        $stid = db_query($conn, $sql, [
            'sid' => $student_id,
            'type_id' => $selected_type['TYPE_ID'],
            'ref' => $reference_number,
            'status_id' => $submitted_status_id,
            'data' => $json_data
        ]);

        $new_app_id = db_last_insert_id($conn, 'seq_app_id');

        if ($new_app_id) {
            // Insert initial status history record
            db_query($conn, "
                INSERT INTO APPLICATION_STATUS_HISTORY (application_id, status_id, changed_by, comments)
                VALUES (:app_id, :status_id, :sid, 'Application submitted by student')
            ", [
                'app_id' => $new_app_id,
                'status_id' => $submitted_status_id,
                'sid' => $student_id
            ]);

            $success = true;
            $reference_number = '';
        } else {
            $errors['general'] = 'Failed to create application. Please try again.';
        }
    }
}

db_close($conn);
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Apply New Application</h1>
            <p>Select application type and fill in the required information</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Application submitted successfully!
            </div>
            <p style="margin-bottom: 20px;">
                <a href="<?php echo base_url('student/my_applications.php'); ?>" class="btn btn-primary">View My Applications</a>
                <a href="<?php echo base_url('student/dashboard.php'); ?>" class="btn btn-small">Back to Dashboard</a>
            </p>
        <?php elseif ($selected_type && !empty($fields)): ?>
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
            <?php endif; ?>

            <div class="dashboard-section">
                <div class="section-header">
                    <h2>
                        <?php echo e($selected_type['TYPE_NAME']); ?> Application
                        <?php if ($selected_type['FEE_AMOUNT'] > 0): ?>
                            <small>(Fee: <?php echo e(number_format($selected_type['FEE_AMOUNT'], 2)); ?> BDT)</small>
                        <?php endif; ?>
                    </h2>
                    <a href="<?php echo base_url('student/apply.php'); ?>" class="btn btn-small">Change Type</a>
                </div>

                <?php if (!empty($selected_type['DESCRIPTION'])): ?>
                    <p style="color: #666; margin-bottom: 20px;"><?php echo e($selected_type['DESCRIPTION']); ?></p>
                <?php endif; ?>

                <form method="POST" action="<?php echo base_url('student/apply.php?type=' . $selected_type['TYPE_ID']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="type_id" value="<?php echo e($selected_type['TYPE_ID']); ?>">

                    <?php foreach ($fields as $field): ?>
                        <div class="form-group">
                            <label for="<?php echo e($field['name']); ?>">
                                <?php echo e($field['label']); ?> <?php echo ($field['required']) ? '<span style="color: red;">*</span>' : ''; ?>
                            </label>

                            <?php if ($field['type'] === 'textarea'): ?>
                                <textarea id="<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>"
                                          rows="<?php echo $field['rows'] ?? 3; ?>"
                                          class="<?php echo isset($errors[$field['name']]) ? 'input-error' : ''; ?>"
                                ><?php echo e($_POST[$field['name']] ?? ''); ?></textarea>
                            <?php elseif ($field['type'] === 'select'): ?>
                                <select id="<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>"
                                        class="<?php echo isset($errors[$field['name']]) ? 'input-error' : ''; ?>">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($field['options'] as $val => $label): ?>
                                        <option value="<?php echo e($val); ?>"
                                            <?php echo (($_POST[$field['name']] ?? '') === $val) ? 'selected' : ''; ?>>
                                            <?php echo e($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="<?php echo e($field['type']); ?>"
                                       id="<?php echo e($field['name']); ?>"
                                       name="<?php echo e($field['name']); ?>"
                                       <?php echo ($field['type'] === 'number') ? 'step="' . e($field['step']) . '" min="' . e($field['min']) . '" max="' . e($field['max']) . '"' : ''; ?>
                                       required
                                       value="<?php echo e($_POST[$field['name']] ?? ''); ?>"
                                       class="<?php echo isset($errors[$field['name']]) ? 'input-error' : ''; ?>">
                            <?php endif; ?>
                            <?php if (isset($errors[$field['name']])): ?>
                                <span class="error-text"><?php echo e($errors[$field['name']]); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">
                            <strong>Note:</strong> Document upload is not yet available. You will be able to upload required documents after submission.
                        </p>
                        <button type="submit" class="btn btn-primary btn-block">Submit Application</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Type Selection -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Select Application Type</h2>
                    <a href="<?php echo base_url('student/dashboard.php'); ?>" class="btn btn-small">Back to Dashboard</a>
                </div>

                <div class="features-grid">
                    <?php foreach ($app_types as $t): ?>
                        <a href="<?php echo base_url('student/apply.php?type=' . $t['TYPE_ID']); ?>" class="feature-card" style="text-decoration: none; cursor: pointer;">
                            <h3><?php echo e($t['TYPE_NAME']); ?></h3>
                            <p><?php echo e($t['DESCRIPTION']); ?></p>
                            <?php if ($t['FEE_AMOUNT'] > 0): ?>
                                <p style="color: #3949ab; font-weight: 600; margin-top: 10px;">Fee: <?php echo e(number_format($t['FEE_AMOUNT'], 2)); ?> BDT</p>
                            <?php else: ?>
                                <p style="color: #28a745; font-weight: 600; margin-top: 10px;">No fee required</p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
