-- ============================================
-- University Application Management System
-- Oracle Database Seed Data
-- Run this AFTER schema.sql
-- ============================================

-- NOTE: Password hashes below correspond to 'password123'
-- In production, generate hashes using PHP password_hash()

-- ============================================
-- 1. USERS
-- ============================================

INSERT INTO USERS (user_id, email, password_hash, full_name, role, phone, department, is_active)
VALUES (1, 'admin@uni.edu', '$2y$10$kUgrIFjGMSGA21lnlQ999.XXS40Edf7FiDBbeSaNhQ.AYoI43VKoi', 'System Administrator', 'admin', '01700000001', 'IT', 'Y');

INSERT INTO USERS (user_id, email, password_hash, full_name, role, phone, department, is_active)
VALUES (2, 'reviewer1@uni.edu', '$2y$10$sb56zGVrT6tS80RdFZ39/uihmahs0P4mUw6UgcM8ghbQYOpR6o9Cy', 'Dr. Ahmed Rahman', 'reviewer', '01700000002', 'Computer Science', 'Y');

INSERT INTO USERS (user_id, email, password_hash, full_name, role, phone, department, is_active)
VALUES (3, 'reviewer2@uni.edu', '$2y$10$UfeFpDBZspcq5tJHP5pFD.PFizPBS3wUDJBqkHIvrSQsYe3gkaShO', 'Prof. Sara Khan', 'reviewer', '01700000003', 'Mathematics', 'Y');

INSERT INTO USERS (user_id, email, password_hash, full_name, role, phone, department, is_active)
VALUES (4, 'student1@uni.edu', '$2y$10$3m2leq57nDG6r0Bw0DQP8.eJ0Z8KhPdIYuJwt.f5Vh5vK8q37/8zu', 'Md. Hasan Ali', 'student', '01700000004', 'Computer Science', 'Y');

INSERT INTO USERS (user_id, email, password_hash, full_name, role, phone, department, is_active)
VALUES (5, 'student2@uni.edu', '$2y$10$vM3axFVfr.VxZLtmB7xLAua4YGBWn.uC7YZx/qqeGYCYRhRsIQp7G', 'Fatima Begum', 'student', '01700000005', 'Mathematics', 'Y');

INSERT INTO USERS (user_id, email, password_hash, full_name, role, phone, department, is_active)
VALUES (6, 'student3@uni.edu', '$2y$10$RUr6PujXhGNw/eQCcaB9AOXxQFcSgUiwNwzwtj0Il0EKFnOg1bJyy', 'Kamal Hossain', 'student', '01700000006', 'Physics', 'Y');

-- ============================================
-- 2. APPLICATION_TYPES
-- ============================================

INSERT INTO APPLICATION_TYPES (type_id, type_code, type_name, description, requires_payment, fee_amount, is_active)
VALUES (1, 'admission', 'Admission', 'Apply for admission to undergraduate and postgraduate programs', 'Y', 500.00, 'Y');

INSERT INTO APPLICATION_TYPES (type_id, type_code, type_name, description, requires_payment, fee_amount, is_active)
VALUES (2, 'scholarship', 'Scholarship', 'Submit scholarship applications with supporting documents', 'N', 0.00, 'Y');

INSERT INTO APPLICATION_TYPES (type_id, type_code, type_name, description, requires_payment, fee_amount, is_active)
VALUES (3, 'transcript', 'Transcript Request', 'Request official academic transcripts online', 'Y', 50.00, 'Y');

INSERT INTO APPLICATION_TYPES (type_id, type_code, type_name, description, requires_payment, fee_amount, is_active)
VALUES (4, 'certificate', 'Certificate Request', 'Apply for enrollment, completion, or other certificates', 'Y', 100.00, 'Y');

INSERT INTO APPLICATION_TYPES (type_id, type_code, type_name, description, requires_payment, fee_amount, is_active)
VALUES (5, 'id_card', 'ID Card Request', 'Request or renew your student identification card', 'Y', 200.00, 'Y');

INSERT INTO APPLICATION_TYPES (type_id, type_code, type_name, description, requires_payment, fee_amount, is_active)
VALUES (6, 'dept_course_change', 'Department/Course Change', 'Submit requests to change your department or courses', 'Y', 150.00, 'Y');

-- ============================================
-- 3. APPLICATION_STATUS
-- ============================================

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (1, 'draft', 'Draft', 'Application saved but not yet submitted', 'N', 1, 'Y');

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (2, 'submitted', 'Submitted', 'Application submitted and awaiting review assignment', 'N', 2, 'Y');

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (3, 'under_review', 'Under Review', 'Reviewer is currently evaluating the application', 'N', 3, 'Y');

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (4, 'reviewed', 'Reviewed', 'Review completed, awaiting admin decision', 'N', 4, 'Y');

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (5, 'approved', 'Approved', 'Application approved by admin', 'N', 5, 'Y');

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (6, 'rejected', 'Rejected', 'Application rejected by admin', 'N', 6, 'Y');

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (7, 'needs_review', 'Needs More Information', 'Additional documents or information required', 'N', 7, 'Y');

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (8, 'payment_pending', 'Payment Pending', 'Approved but awaiting payment verification', 'N', 8, 'Y');

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (9, 'completed', 'Completed', 'Application process fully completed', 'Y', 9, 'Y');

INSERT INTO APPLICATION_STATUS (status_id, status_code, status_name, description, is_final, sort_order, is_active)
VALUES (10, 'closed', 'Closed', 'Application closed after rejection', 'Y', 10, 'Y');

-- ============================================
-- 4. APPLICATIONS
-- ============================================

-- Application 1: Student1 -> Admission -> Submitted -> Reviewer1
INSERT INTO APPLICATIONS (application_id, student_id, type_id, reference_number, current_status_id, application_data, reviewer_id, submitted_at, created_at, updated_at)
VALUES (1, 4, 1, 'UAMS-2026-0001', 2, '{"gpa":"3.8","previous_university":"University of Dhaka","program":"Computer Science","motivation":"I am passionate about AI research."}', 2, SYSDATE, SYSDATE, SYSDATE);

-- Application 2: Student2 -> Transcript -> Completed -> Reviewer2
INSERT INTO APPLICATIONS (application_id, student_id, type_id, reference_number, current_status_id, application_data, reviewer_id, submitted_at, created_at, updated_at)
VALUES (2, 5, 3, 'UAMS-2026-0002', 9, '{"purpose":"Higher studies abroad","destination":"USA","university":"MIT"}', 3, SYSDATE - 7, SYSDATE - 7, SYSDATE);

-- Application 3: Student3 -> Scholarship -> Reviewed -> Reviewer1
INSERT INTO APPLICATIONS (application_id, student_id, type_id, reference_number, current_status_id, application_data, reviewer_id, submitted_at, created_at, updated_at)
VALUES (3, 6, 2, 'UAMS-2026-0003', 4, '{"gpa":"3.95","previous_university":"BUET","program":"Physics","achievements":"Dean list all semesters"}', 2, SYSDATE - 3, SYSDATE - 3, SYSDATE);

-- ============================================
-- 5. DOCUMENTS
-- ============================================

INSERT INTO DOCUMENTS (document_id, application_id, original_filename, stored_filename, file_path, file_size, mime_type, uploaded_by, uploaded_at)
VALUES (1, 1, 'hsc_certificate.pdf', 'app_1_doc_abc123.pdf', 'uploads/documents/app_1_doc_abc123.pdf', 2048000, 'application/pdf', 4, SYSDATE);

INSERT INTO DOCUMENTS (document_id, application_id, original_filename, stored_filename, file_path, file_size, mime_type, uploaded_by, uploaded_at)
VALUES (2, 1, 'photo.jpg', 'app_1_doc_def456.jpg', 'uploads/documents/app_1_doc_def456.jpg', 512000, 'image/jpeg', 4, SYSDATE);

INSERT INTO DOCUMENTS (document_id, application_id, original_filename, stored_filename, file_path, file_size, mime_type, uploaded_by, uploaded_at)
VALUES (3, 2, 'transcript_request_form.pdf', 'app_2_doc_ghi789.pdf', 'uploads/documents/app_2_doc_ghi789.pdf', 1024000, 'application/pdf', 5, SYSDATE - 7);

INSERT INTO DOCUMENTS (document_id, application_id, original_filename, stored_filename, file_path, file_size, mime_type, uploaded_by, uploaded_at)
VALUES (4, 3, 'scholarship_essay.pdf', 'app_3_doc_jkl012.pdf', 'uploads/documents/app_3_doc_jkl012.pdf', 3072000, 'application/pdf', 6, SYSDATE - 3);

-- ============================================
-- 6. REVIEWS
-- ============================================

INSERT INTO REVIEWS (review_id, application_id, reviewer_id, recommendation, comments, review_date, created_at, updated_at)
VALUES (1, 1, 2, 'approve', 'Candidate has excellent academic record. GPA 3.8 meets the requirement. Recommended for admission.', SYSDATE - 1, SYSDATE - 1, SYSDATE - 1);

INSERT INTO REVIEWS (review_id, application_id, reviewer_id, recommendation, comments, review_date, created_at, updated_at)
VALUES (2, 2, 3, 'approve', 'Transcript request verified. Student has no outstanding dues. Approved for processing.', SYSDATE - 5, SYSDATE - 5, SYSDATE - 5);

INSERT INTO REVIEWS (review_id, application_id, reviewer_id, recommendation, comments, review_date, created_at, updated_at)
VALUES (3, 3, 2, 'request_info', 'Please provide proof of research publication or conference participation to strengthen the scholarship application.', SYSDATE, SYSDATE, SYSDATE);

-- ============================================
-- 7. PAYMENTS
-- ============================================

INSERT INTO PAYMENTS (payment_id, application_id, amount, payment_method, transaction_ref, payment_date, verified_by, status, receipt_path, created_at, updated_at)
VALUES (1, 2, 50.00, 'bank_transfer', 'TXN-2026-001', SYSDATE - 6, 1, 'verified', 'uploads/documents/receipt_app2.jpg', SYSDATE - 6, SYSDATE - 6);

INSERT INTO PAYMENTS (payment_id, application_id, amount, payment_method, transaction_ref, payment_date, verified_by, status, receipt_path, created_at, updated_at)
VALUES (2, 1, 500.00, 'bank_transfer', 'TXN-2026-002', NULL, NULL, 'pending', 'uploads/documents/receipt_app1.jpg', SYSDATE, SYSDATE);

-- ============================================
-- 8. APPLICATION_STATUS_HISTORY
-- ============================================

-- History for Application 1 (Admission)
INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (1, 1, 1, 4, 'Application created as draft', SYSDATE - 5);

INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (2, 1, 2, 4, 'Application submitted by student', SYSDATE - 4);

INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (3, 1, 3, 1, 'Reviewer Dr. Ahmed Rahman assigned', SYSDATE - 3);

INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (4, 1, 4, 2, 'Review completed with recommendation: approve', SYSDATE - 1);

-- History for Application 2 (Transcript)
INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (5, 2, 2, 5, 'Application submitted by student', SYSDATE - 7);

INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (6, 2, 3, 1, 'Reviewer Prof. Sara Khan assigned', SYSDATE - 6);

INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (7, 2, 4, 3, 'Review completed with recommendation: approve', SYSDATE - 5);

INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (8, 2, 8, 1, 'Approved. Payment pending.', SYSDATE - 4);

INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (9, 2, 9, 1, 'Payment verified. Application completed.', SYSDATE - 6);

-- History for Application 3 (Scholarship)
INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (10, 3, 2, 6, 'Application submitted by student', SYSDATE - 3);

INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (11, 3, 3, 1, 'Reviewer Dr. Ahmed Rahman assigned', SYSDATE - 2);

INSERT INTO APPLICATION_STATUS_HISTORY (history_id, application_id, status_id, changed_by, comments, changed_at)
VALUES (12, 3, 4, 2, 'Review completed with recommendation: request_info', SYSDATE);

COMMIT;
