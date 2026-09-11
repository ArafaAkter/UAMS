-- ============================================
-- University Application Management System
-- Oracle Database Schema
-- Compatible with Oracle 11g, 12c, 18c
-- ============================================

-- ============================================
-- 1. SEQUENCES (Oracle auto-increment strategy)
-- ============================================

CREATE SEQUENCE seq_user_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_type_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_app_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_doc_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_status_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_history_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_review_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_payment_id START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

-- ============================================
-- 2. TABLES
-- ============================================

-- USERS: Stores all system users (Students, Reviewers, Admins)
CREATE TABLE USERS (
    user_id         NUMBER(10) NOT NULL PRIMARY KEY,
    email           VARCHAR2(255) UNIQUE NOT NULL,
    password_hash   VARCHAR2(255) NOT NULL,
    full_name       VARCHAR2(255) NOT NULL,
    role            VARCHAR2(20) NOT NULL CHECK (role IN ('student', 'reviewer', 'admin')),
    phone           VARCHAR2(50),
    department      VARCHAR2(100),
    is_active       CHAR(1) DEFAULT 'Y' NOT NULL CHECK (is_active IN ('Y', 'N')),
    created_at      DATE DEFAULT SYSDATE NOT NULL,
    updated_at      DATE DEFAULT SYSDATE NOT NULL
);

-- APPLICATION_TYPES: Master data for the six application types
CREATE TABLE APPLICATION_TYPES (
    type_id          NUMBER(10) NOT NULL PRIMARY KEY,
    type_code        VARCHAR2(50) UNIQUE NOT NULL,
    type_name        VARCHAR2(100) UNIQUE NOT NULL,
    description      VARCHAR2(500),
    requires_payment CHAR(1) DEFAULT 'N' NOT NULL CHECK (requires_payment IN ('Y', 'N')),
    fee_amount       NUMBER(10,2) DEFAULT 0 NOT NULL,
    is_active        CHAR(1) DEFAULT 'Y' NOT NULL CHECK (is_active IN ('Y', 'N')),
    created_at       DATE DEFAULT SYSDATE NOT NULL
);

-- APPLICATION_STATUS: Lookup table for all possible statuses
CREATE TABLE APPLICATION_STATUS (
    status_id    NUMBER(10) NOT NULL PRIMARY KEY,
    status_code  VARCHAR2(50) UNIQUE NOT NULL,
    status_name  VARCHAR2(100) NOT NULL,
    description  VARCHAR2(255),
    is_final     CHAR(1) DEFAULT 'N' NOT NULL CHECK (is_final IN ('Y', 'N')),
    sort_order   NUMBER(5),
    is_active    CHAR(1) DEFAULT 'Y' NOT NULL CHECK (is_active IN ('Y', 'N'))
);

-- APPLICATIONS: Core transactional table for each application instance
CREATE TABLE APPLICATIONS (
    application_id    NUMBER(10) NOT NULL PRIMARY KEY,
    student_id        NUMBER(10) NOT NULL,
    type_id           NUMBER(10) NOT NULL,
    reference_number  VARCHAR2(50) UNIQUE NOT NULL,
    current_status_id NUMBER(10) NOT NULL,
    application_data  CLOB,
    reviewer_id       NUMBER(10),
    submitted_at      DATE,
    created_at        DATE DEFAULT SYSDATE NOT NULL,
    updated_at        DATE DEFAULT SYSDATE NOT NULL,
    CONSTRAINT fk_app_student FOREIGN KEY (student_id) REFERENCES USERS(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_app_type FOREIGN KEY (type_id) REFERENCES APPLICATION_TYPES(type_id),
    CONSTRAINT fk_app_status FOREIGN KEY (current_status_id) REFERENCES APPLICATION_STATUS(status_id),
    CONSTRAINT fk_app_reviewer FOREIGN KEY (reviewer_id) REFERENCES USERS(user_id) ON DELETE SET NULL
);

-- DOCUMENTS: Stores metadata for files uploaded against applications
CREATE TABLE DOCUMENTS (
    document_id      NUMBER(10) NOT NULL PRIMARY KEY,
    application_id   NUMBER(10) NOT NULL,
    original_filename VARCHAR2(255) NOT NULL,
    stored_filename  VARCHAR2(255) NOT NULL,
    file_path        VARCHAR2(500) NOT NULL,
    file_size        NUMBER(10),
    mime_type        VARCHAR2(100),
    uploaded_by      NUMBER(10) NOT NULL,
    uploaded_at      DATE DEFAULT SYSDATE NOT NULL,
    verification_status VARCHAR2(20) DEFAULT 'pending' NOT NULL,
    CONSTRAINT fk_doc_app FOREIGN KEY (application_id) REFERENCES APPLICATIONS(application_id) ON DELETE CASCADE,
    CONSTRAINT fk_doc_user FOREIGN KEY (uploaded_by) REFERENCES USERS(user_id),
    CONSTRAINT chk_doc_verification_status CHECK (verification_status IN ('pending', 'approved', 'rejected', 'needs_correction'))
);

-- APPLICATION_STATUS_HISTORY: Immutable audit trail of status changes
CREATE TABLE APPLICATION_STATUS_HISTORY (
    history_id   NUMBER(10) NOT NULL PRIMARY KEY,
    application_id NUMBER(10) NOT NULL,
    status_id    NUMBER(10) NOT NULL,
    changed_by   NUMBER(10) NOT NULL,
    comments     CLOB,
    changed_at   DATE DEFAULT SYSDATE NOT NULL,
    CONSTRAINT fk_hist_app FOREIGN KEY (application_id) REFERENCES APPLICATIONS(application_id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_status FOREIGN KEY (status_id) REFERENCES APPLICATION_STATUS(status_id),
    CONSTRAINT fk_hist_user FOREIGN KEY (changed_by) REFERENCES USERS(user_id)
);

-- REVIEWS: Stores reviewer evaluations and recommendations
CREATE TABLE REVIEWS (
    review_id      NUMBER(10) NOT NULL PRIMARY KEY,
    application_id NUMBER(10) NOT NULL,
    reviewer_id    NUMBER(10) NOT NULL,
    recommendation VARCHAR2(20) NOT NULL CHECK (recommendation IN ('approve', 'reject', 'request_info')),
    comments       CLOB,
    review_date    DATE DEFAULT SYSDATE NOT NULL,
    created_at     DATE DEFAULT SYSDATE NOT NULL,
    updated_at     DATE DEFAULT SYSDATE NOT NULL,
    CONSTRAINT fk_review_app FOREIGN KEY (application_id) REFERENCES APPLICATIONS(application_id) ON DELETE CASCADE,
    CONSTRAINT fk_reviewer FOREIGN KEY (reviewer_id) REFERENCES USERS(user_id) ON DELETE CASCADE,
    CONSTRAINT uk_review_app_reviewer UNIQUE (application_id, reviewer_id)
);

-- PAYMENTS: Records payment transactions linked to applications
CREATE TABLE PAYMENTS (
    payment_id          NUMBER(10) NOT NULL PRIMARY KEY,
    application_id      NUMBER(10) NOT NULL,
    amount              NUMBER(10,2) NOT NULL,
    payment_method      VARCHAR2(50) NOT NULL,
    transaction_ref     VARCHAR2(100),
    payment_date        DATE,
    verified_by         NUMBER(10),
    status              VARCHAR2(20) DEFAULT 'pending' NOT NULL CHECK (status IN ('pending', 'verified', 'failed')),
    receipt_path        VARCHAR2(500),
    created_at          DATE DEFAULT SYSDATE NOT NULL,
    updated_at          DATE DEFAULT SYSDATE NOT NULL,
    CONSTRAINT fk_payment_app FOREIGN KEY (application_id) REFERENCES APPLICATIONS(application_id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_verifier FOREIGN KEY (verified_by) REFERENCES USERS(user_id) ON DELETE SET NULL
);

-- ============================================
-- 3. TRIGGERS (Auto-generate IDs from sequences)
-- ============================================

CREATE OR REPLACE TRIGGER trg_users_bi
BEFORE INSERT ON USERS
FOR EACH ROW
WHEN (new.user_id IS NULL)
BEGIN
    SELECT seq_user_id.NEXTVAL INTO :new.user_id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER trg_types_bi
BEFORE INSERT ON APPLICATION_TYPES
FOR EACH ROW
WHEN (new.type_id IS NULL)
BEGIN
    SELECT seq_type_id.NEXTVAL INTO :new.type_id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER trg_status_bi
BEFORE INSERT ON APPLICATION_STATUS
FOR EACH ROW
WHEN (new.status_id IS NULL)
BEGIN
    SELECT seq_status_id.NEXTVAL INTO :new.status_id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER trg_apps_bi
BEFORE INSERT ON APPLICATIONS
FOR EACH ROW
WHEN (new.application_id IS NULL)
BEGIN
    SELECT seq_app_id.NEXTVAL INTO :new.application_id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER trg_docs_bi
BEFORE INSERT ON DOCUMENTS
FOR EACH ROW
WHEN (new.document_id IS NULL)
BEGIN
    SELECT seq_doc_id.NEXTVAL INTO :new.document_id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER trg_history_bi
BEFORE INSERT ON APPLICATION_STATUS_HISTORY
FOR EACH ROW
WHEN (new.history_id IS NULL)
BEGIN
    SELECT seq_history_id.NEXTVAL INTO :new.history_id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER trg_reviews_bi
BEFORE INSERT ON REVIEWS
FOR EACH ROW
WHEN (new.review_id IS NULL)
BEGIN
    SELECT seq_review_id.NEXTVAL INTO :new.review_id FROM dual;
END;
/

CREATE OR REPLACE TRIGGER trg_payments_bi
BEFORE INSERT ON PAYMENTS
FOR EACH ROW
WHEN (new.payment_id IS NULL)
BEGIN
    SELECT seq_payment_id.NEXTVAL INTO :new.payment_id FROM dual;
END;
/

-- ============================================
-- 3b. RBAC TRIGGERS (Role-based access control at database level)
-- ============================================

-- Only admins may verify payments (defense-in-depth RBAC at database level)
CREATE OR REPLACE TRIGGER trg_payments_bu
BEFORE UPDATE OF status, verified_by ON PAYMENTS
FOR EACH ROW
WHEN (new.status = 'verified' OR (new.status = 'failed' AND new.verified_by IS NOT NULL))
DECLARE
    v_role USERS.role%TYPE;
BEGIN
    IF :new.status = 'verified' THEN
        IF :new.verified_by IS NULL THEN
            RAISE_APPLICATION_ERROR(-20001, 'Payment verification requires an administrator (verified_by).');
        END IF;
        SELECT role INTO v_role FROM USERS WHERE user_id = :new.verified_by;
        IF v_role != 'admin' THEN
            RAISE_APPLICATION_ERROR(-20001, 'Only administrators may verify payments.');
        END IF;
    ELSIF :new.verified_by IS NOT NULL THEN
        SELECT role INTO v_role FROM USERS WHERE user_id = :new.verified_by;
        IF v_role != 'admin' THEN
            RAISE_APPLICATION_ERROR(-20001, 'Only administrators may verify payments.');
        END IF;
    END IF;
END;
/

-- Note: Reviewer payment access is enforced at the PHP application layer via require_role()
-- and status ID whitelisting in reviewer/* pages. Database-level triggers cannot distinguish
-- the application user making the update (all updates come through the same Oracle DB user),
-- so reviewer RBAC for status transitions is handled in application code where the
-- authenticated session user's role is known.

-- ============================================
-- 4. INDEXES (Performance optimization)
-- ============================================

-- USERS indexes
CREATE INDEX idx_users_role ON USERS(role);
CREATE INDEX idx_users_active ON USERS(is_active);

-- APPLICATIONS indexes
CREATE INDEX idx_apps_student ON APPLICATIONS(student_id);
CREATE INDEX idx_apps_type ON APPLICATIONS(type_id);
CREATE INDEX idx_apps_status ON APPLICATIONS(current_status_id);
CREATE INDEX idx_apps_reviewer ON APPLICATIONS(reviewer_id);

-- DOCUMENTS indexes
CREATE INDEX idx_docs_app ON DOCUMENTS(application_id);
CREATE INDEX idx_docs_uploader ON DOCUMENTS(uploaded_by);

-- APPLICATION_STATUS_HISTORY indexes
CREATE INDEX idx_history_app ON APPLICATION_STATUS_HISTORY(application_id);
CREATE INDEX idx_history_status ON APPLICATION_STATUS_HISTORY(status_id);
CREATE INDEX idx_history_changed ON APPLICATION_STATUS_HISTORY(changed_by);
CREATE INDEX idx_history_date ON APPLICATION_STATUS_HISTORY(changed_at);

-- REVIEWS indexes
CREATE INDEX idx_reviews_app ON REVIEWS(application_id);
CREATE INDEX idx_reviews_reviewer ON REVIEWS(reviewer_id);

-- PAYMENTS indexes
CREATE INDEX idx_payments_app ON PAYMENTS(application_id);
CREATE INDEX idx_payments_status ON PAYMENTS(status);
CREATE INDEX idx_payments_verifier ON PAYMENTS(verified_by);

COMMIT;
