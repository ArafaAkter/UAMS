<?php
// student/documents.php
// Upload and view documents for the student's own applications

$page_title = 'Documents';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/header.php';

require_role('student');

$conn = db_connect();
$student_id = current_user_id();

$errors = [];
$success = false;

// Fetch the student's submitted applications (for the upload form dropdown)
$applications = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number, t.type_name
    FROM APPLICATIONS a
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    WHERE a.student_id = :sid
    ORDER BY a.created_at DESC
", ['sid' => $student_id]);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $application_id = trim($_POST['application_id'] ?? '');

    // Validate application ownership
    if (empty($application_id) || !is_numeric($application_id)) {
        $errors['application_id'] = 'Please select a valid application.';
    } else {
        $app_check = db_fetch_one($conn, "
            SELECT application_id FROM APPLICATIONS
            WHERE application_id = :app_id AND student_id = :sid
        ", ['app_id' => $application_id, 'sid' => $student_id]);

        if (!$app_check) {
            $errors['application_id'] = 'Invalid application. You do not have permission to upload documents for this application.';
        }
    }

    // Check if file was uploaded without errors
    if (empty($errors) && (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE)) {
        $errors['document'] = 'Please select a file to upload.';
    }

    if (empty($errors)) {
        $uploaded = $_FILES['document'];

        // Check for upload errors
        if ($uploaded['error'] !== UPLOAD_ERR_OK) {
            $errors['document'] = 'File upload failed. Please try again.';
        }
    }

    if (empty($errors)) {
        $original_filename = $uploaded['name'];
        $file_size = $uploaded['size'];
        $tmp_name = $uploaded['tmp_name'];
        $error_code = $uploaded['error'];

        // Validate file size
        if ($file_size > UPLOAD_MAX_SIZE) {
            $errors['document'] = 'File is too large. Maximum size allowed: ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB.';
        }

        // Validate file size (PHP limit)
        if ($file_size <= 0) {
            $errors['document'] = 'Invalid file. File size is 0 bytes.';
        }
    }

    if (empty($errors)) {
        // Extract file extension
        $path_info = pathinfo($original_filename);
        $extension = strtolower($path_info['extension'] ?? '');

        // Validate file extension (prevent executable files)
        if (empty($extension) || !in_array($extension, ALLOWED_EXTENSIONS)) {
            $errors['document'] = 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_EXTENSIONS) . '.';
        }

        // Additional check: ensure no double extensions (e.g., file.php.txt)
        $allowed_pattern = '/\.(?:' . implode('|', array_map(function($ext) { return preg_quote($ext); }, ALLOWED_EXTENSIONS)) . ')$/i';
        if (!preg_match($allowed_pattern, $original_filename)) {
            $errors['document'] = 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_EXTENSIONS) . '.';
        }
    }

    if (empty($errors)) {
        // Determine MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);

        // Validate MIME type whitelist
        $allowed_mimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        if (!in_array($mime_type, $allowed_mimes)) {
            $errors['document'] = 'File content type not allowed: ' . $mime_type;
        }
    }

    if (empty($errors)) {
        // Generate safe unique filename: {random}_{timestamp}.{ext}
        $random_part = bin2hex(random_bytes(8));
        $timestamp = time();
        $stored_filename = $random_part . '_' . $timestamp . '.' . $extension;
        $upload_dir = __DIR__ . '/../uploads/documents/';
        $file_path = 'uploads/documents/' . $stored_filename;

        // Ensure upload directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($tmp_name, $upload_dir . $stored_filename)) {
            $errors['document'] = 'Failed to save file. Please try again.';
        }
    }

    if (empty($errors)) {
        // Insert document record into database
        $sql = "INSERT INTO DOCUMENTS (application_id, original_filename, stored_filename, file_path, file_size, mime_type, uploaded_by, verification_status)
                VALUES (:app_id, :orig_name, :stored_name, :file_path, :file_size, :mime_type, :uploaded_by, :verif_status)";
        $stid = db_query($conn, $sql, [
            'app_id' => $application_id,
            'orig_name' => $original_filename,
            'stored_name' => $stored_filename,
            'file_path' => $file_path,
            'file_size' => $file_size,
            'mime_type' => $mime_type,
            'uploaded_by' => $student_id,
            'verif_status' => 'pending'
        ]);

        $doc_id = db_last_insert_id($conn, 'seq_doc_id');

        if ($doc_id) {
            $success = true;
        } else {
            // Clean up uploaded file if DB insert failed
            unlink($upload_dir . $stored_filename);
            $errors['general'] = 'Failed to save document record. Please try again.';
        }
    }
}

// Fetch documents belonging to the student's applications only (with verification status)
$documents = db_fetch_all($conn, "
    SELECT d.document_id, d.original_filename, d.stored_filename, d.file_path, d.file_size, d.mime_type, d.uploaded_at, d.verification_status,
           a.application_id, a.reference_number,
           t.type_name
    FROM DOCUMENTS d
    JOIN APPLICATIONS a ON d.application_id = a.application_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    WHERE a.student_id = :sid
    ORDER BY d.uploaded_at DESC
", ['sid' => $student_id]);

// Count documents
$doc_count = db_fetch_value($conn, "
    SELECT COUNT(*) FROM DOCUMENTS d
    JOIN APPLICATIONS a ON d.application_id = a.application_id
    WHERE a.student_id = :sid
", ['sid' => $student_id]);

db_close($conn);

function format_file_size($bytes) {
    if (!$bytes) return 'N/A';
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function verification_badge($status) {
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
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Documents</h1>
            <p>Upload and manage documents for your applications</p>
        </div>

        <!-- Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($doc_count); ?></h3>
                <p>Total Documents</p>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Upload New Document</h2>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    Document uploaded successfully! Your document is pending verification.
                </div>
            <?php endif; ?>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
            <?php endif; ?>

            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <p>You have no applications to attach documents to.</p>
                    <p><a href="<?php echo base_url('student/apply.php'); ?>" class="btn btn-primary">Submit an Application First</a></p>
                </div>
            <?php else: ?>
                <form method="POST" action="<?php echo base_url('student/documents.php'); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                    <div class="form-group">
                        <label for="application_id">Select Application *</label>
                        <select id="application_id" name="application_id" required
                                class="<?php echo isset($errors['application_id']) ? 'input-error' : ''; ?>">
                            <option value="">-- Select an application --</option>
                            <?php foreach ($applications as $app): ?>
                                <option value="<?php echo e($app['APPLICATION_ID']); ?>"
                                    <?php echo (($_POST['application_id'] ?? '') == $app['APPLICATION_ID']) ? 'selected' : ''; ?>>
                                    <?php echo e($app['REFERENCE_NUMBER'] . ' - ' . $app['TYPE_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['application_id'])): ?>
                            <span class="error-text"><?php echo e($errors['application_id']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="document">Document File *</label>
                        <input type="file" id="document" name="document" required
                               class="<?php echo isset($errors['document']) ? 'input-error' : ''; ?>">
                        <p style="color: #666; font-size: 0.85rem; margin-top: 5px;">
                            Allowed types: <?php echo e(implode(', ', ALLOWED_EXTENSIONS)); ?> |
                            Max size: <?php echo e(UPLOAD_MAX_SIZE / 1024 / 1024); ?>MB
                        </p>
                        <?php if (isset($errors['document'])): ?>
                            <span class="error-text"><?php echo e($errors['document']); ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Upload Document</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Documents List -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>My Documents</h2>
                <a href="<?php echo base_url('student/dashboard.php'); ?>" class="btn btn-small">Back to Dashboard</a>
            </div>

            <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <p>You have no uploaded documents yet.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Type</th>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>MIME Type</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><?php echo e($doc['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($doc['TYPE_NAME']); ?></td>
                                    <td><?php echo e($doc['ORIGINAL_FILENAME']); ?></td>
                                    <td><?php echo e(format_file_size($doc['FILE_SIZE'])); ?></td>
                                    <td><?php echo e($doc['MIME_TYPE']); ?></td>
                                    <td><?php echo verification_badge($doc['VERIFICATION_STATUS'] ?? 'pending'); ?></td>
                                    <td><?php echo e(format_date($doc['UPLOADED_AT'])); ?></td>
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
