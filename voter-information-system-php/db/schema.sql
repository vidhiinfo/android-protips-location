-- =====================================================================
-- Voter Information System (PHP / Laravel) - MySQL 8 / MariaDB 10.5+ schema
--
-- SHARED HOSTING (cPanel, GoDaddy, HostGator ...):
--   1. cPanel > MySQL Databases: create the database and a user, add the user
--      to the database with ALL PRIVILEGES.
--   2. phpMyAdmin > select that database > Import > choose this file > Go.
--   (No CREATE DATABASE / CREATE USER here - shared hosts do not allow them.)
--
-- Same tables as the Java version: both versions can use one database.
-- Every table is utf8mb4 so Hindi, Urdu, Bengali, Tamil ... can be stored.
-- =====================================================================

SET NAMES utf8mb4;
-- ---------------------------------------------------------------- voters
CREATE TABLE IF NOT EXISTS voters (
    id                  BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    epic_no             VARCHAR(20)  NOT NULL,
    name                VARCHAR(120) NOT NULL COMMENT 'name as on the roll (English)',
    name_local          VARCHAR(120)          COMMENT 'name in the regional language',
    relative_name       VARCHAR(120),
    relative_name_local VARCHAR(120),
    relation_type       VARCHAR(10),
    gender              VARCHAR(10)  NOT NULL,
    age                 INT,
    assembly_no         INT          NOT NULL,
    assembly_name       VARCHAR(100),
    booth_no            INT          NOT NULL,
    booth_name          VARCHAR(200),
    serial_no           INT,
    house_no            VARCHAR(30),
    address             VARCHAR(300),
    address_local       VARCHAR(300),
    village_ward        VARCHAR(100),
    ward_no             INT                   COMMENT 'municipal ward no.',
    district            VARCHAR(100),
    pincode             VARCHAR(6),
    mobile              VARCHAR(10),
    alt_mobile          VARCHAR(10),
    email               VARCHAR(120),
    category            VARCHAR(50),
    remarks             VARCHAR(500),
    membership_no       VARCHAR(50)           COMMENT 'party membership no.',
    dob                 DATE,
    anniversary         DATE                  COMMENT 'marriage anniversary',
    deleted             TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = deleted from the electoral roll',
    shift1              VARCHAR(100),
    shift2              VARCHAR(100),
    place_of_origin     VARCHAR(150),
    current_place       VARCHAR(150),
    linked_with         VARCHAR(150),
    social_status       VARCHAR(100),
    political_status    VARCHAR(100),
    organisational_status VARCHAR(100),
    beneficiary1        VARCHAR(150)          COMMENT 'government scheme beneficiary',
    beneficiary2        VARCHAR(150),
    beneficiary3        VARCHAR(150),
    caste               VARCHAR(100),
    sub_caste           VARCHAR(100),
    created_at          DATETIME(6)  NOT NULL,
    updated_at          DATETIME(6)  NOT NULL,
    worker_id           BIGINT                COMMENT 'party worker responsible for this voter (workers.id)',
    house_key    VARCHAR(50) GENERATED ALWAYS AS (lower(replace(replace(replace(trim(coalesce(house_no, '')), ' ', ''), '-', ''), '.', ''))) VIRTUAL COMMENT 'house no. without spaces / hyphens / dots, for households',
    CONSTRAINT uk_voter_epic UNIQUE (epic_no),
    INDEX idx_voter_worker (worker_id),
    INDEX idx_voter_name (name),
    INDEX idx_voter_name_local (name_local),
    INDEX idx_voter_assembly_booth (assembly_no, booth_no),
    INDEX idx_voter_list (assembly_no, booth_no, serial_no),
    INDEX idx_voter_house (assembly_no, house_key),
    INDEX idx_voter_mobile (mobile),
    INDEX idx_voter_district (district),
    INDEX idx_voter_village (village_ward),
    INDEX idx_voter_ward_no (ward_no),
    INDEX idx_voter_caste (caste),
    INDEX idx_voter_sub_caste (sub_caste),
    INDEX idx_voter_membership_no (membership_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- masters
CREATE TABLE IF NOT EXISTS assemblies (
    id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assembly_no   INT          NOT NULL,
    name          VARCHAR(100) NOT NULL,
    name_local    VARCHAR(100),
    district      VARCHAR(100),
    state         VARCHAR(100),
    reserved_for  VARCHAR(10),
    CONSTRAINT uk_assembly_no UNIQUE (assembly_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booths (
    id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assembly_no   INT          NOT NULL,
    booth_no      INT          NOT NULL,
    name          VARCHAR(200) NOT NULL,
    name_local    VARCHAR(200),
    location      VARCHAR(300),
    village_ward  VARCHAR(100),
    CONSTRAINT uk_booth UNIQUE (assembly_no, booth_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parties (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    name_local  VARCHAR(150),
    short_name  VARCHAR(20)  NOT NULL,
    symbol      VARCHAR(100),
    color       VARCHAR(7),
    CONSTRAINT uk_party_short UNIQUE (short_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS elections (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150) NOT NULL,
    type           VARCHAR(20)  NOT NULL,
    election_year  INT          NOT NULL,
    poll_date      DATE,
    current        BIT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS candidates (
    id           BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    election_id  BIGINT       NOT NULL,
    assembly_no  INT          NOT NULL,
    name         VARCHAR(120) NOT NULL,
    name_local   VARCHAR(120),
    party_id     BIGINT,
    ballot_no    INT,
    INDEX idx_candidate_contest (election_id, assembly_no),
    CONSTRAINT fk_candidate_election FOREIGN KEY (election_id) REFERENCES elections (id),
    CONSTRAINT fk_candidate_party FOREIGN KEY (party_id) REFERENCES parties (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- results
CREATE TABLE IF NOT EXISTS booth_results (
    id            BIGINT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    election_id   BIGINT      NOT NULL,
    assembly_no   INT         NOT NULL,
    booth_no      INT         NOT NULL,
    candidate_id  BIGINT      NOT NULL,
    votes         INT         NOT NULL,
    entered_by    VARCHAR(50),
    updated_at    DATETIME(6),
    CONSTRAINT uk_booth_result UNIQUE (candidate_id, booth_no),
    INDEX idx_result_booth (election_id, assembly_no, booth_no),
    CONSTRAINT fk_result_candidate FOREIGN KEY (candidate_id) REFERENCES candidates (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- users & audit
-- ---------------------------------------------------------------- party workers and their voters
CREATE TABLE IF NOT EXISTS workers (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    mobile      VARCHAR(15),
    assembly_no INT          NOT NULL,
    booth_no    INT,
    role        VARCHAR(30)  NOT NULL DEFAULT 'VOLUNTEER' COMMENT 'BOOTH_PRESIDENT / BOOTH_AGENT / PANNA_PRAMUKH / VOLUNTEER',
    username    VARCHAR(50)           COMMENT 'login of this worker: sees only the voters assigned to them',
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    notes       VARCHAR(300),
    created_at  DATETIME,
    INDEX idx_worker_booth (assembly_no, booth_no),
    INDEX idx_worker_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- poll day: voters marked as voted
CREATE TABLE IF NOT EXISTS poll_marks (
    id          BIGINT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT      NOT NULL,
    voter_id    BIGINT      NOT NULL,
    assembly_no INT         NOT NULL,
    booth_no    INT         NOT NULL,
    marked_by   VARCHAR(50),
    marked_at   DATETIME    NOT NULL,
    CONSTRAINT uk_poll_mark UNIQUE (election_id, voter_id),
    INDEX idx_poll_booth (election_id, assembly_no, booth_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- grievances / complaints from voters
CREATE TABLE IF NOT EXISTS grievances (
    id          BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    voter_id    BIGINT,
    assembly_no INT           NOT NULL,
    ward_no     INT,
    booth_no    INT,
    name        VARCHAR(120)  NOT NULL,
    mobile      VARCHAR(15),
    category    VARCHAR(30)   NOT NULL,
    subject     VARCHAR(200)  NOT NULL,
    description VARCHAR(2000),
    location    VARCHAR(300),
    priority    VARCHAR(10)   NOT NULL DEFAULT 'NORMAL',
    status      VARCHAR(20)   NOT NULL DEFAULT 'OPEN' COMMENT 'OPEN / IN_PROGRESS / RESOLVED / CLOSED',
    worker_id   BIGINT,
    resolution  VARCHAR(1000),
    created_by  VARCHAR(50),
    created_at  DATETIME,
    updated_at  DATETIME,
    resolved_at DATETIME,
    INDEX idx_grv_booth (assembly_no, booth_no),
    INDEX idx_grv_status (status),
    INDEX idx_grv_voter (voter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Social / Political / Organisational Status masters
CREATE TABLE IF NOT EXISTS social_statuses (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    name_local  VARCHAR(100),
    group_name  VARCHAR(20)           COMMENT 'INFLUENCE / PROFESSION / WORK / OTHER',
    sort_order  INT          NOT NULL DEFAULT 0,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_social_statuses_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS political_statuses (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    name_local  VARCHAR(100),
    leaning     VARCHAR(20)           COMMENT 'S = support, N = neutral, O = opposition, U = not surveyed (used by Analytics)',
    sort_order  INT          NOT NULL DEFAULT 0,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_political_statuses_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS org_statuses (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    name_local  VARCHAR(100),
    level       VARCHAR(20)           COMMENT 'BOOTH / WARD / DISTRICT / FRONT / ELECTED',
    sort_order  INT          NOT NULL DEFAULT 0,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_org_statuses_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- starting values (edit in Masters)
INSERT IGNORE INTO social_statuses (name, name_local, group_name, sort_order, active) VALUES
('Opinion Leader', 'प्रभावशाली व्यक्ति', 'INFLUENCE', 10, 1),
('Religious Leader', 'धार्मिक नेता', 'INFLUENCE', 20, 1),
('Community / Biradari Head', 'समाज प्रमुख', 'INFLUENCE', 30, 1),
('RWA Office Bearer', 'आरडब्ल्यूए पदाधिकारी', 'INFLUENCE', 40, 1),
('Market Association Member', 'व्यापार मंडल सदस्य', 'INFLUENCE', 50, 1),
('Doctor', 'डॉक्टर', 'PROFESSION', 60, 1),
('Teacher', 'शिक्षक', 'PROFESSION', 70, 1),
('Lawyer', 'वकील', 'PROFESSION', 80, 1),
('Engineer', 'इंजीनियर', 'PROFESSION', 90, 1),
('Govt. Employee', 'सरकारी कर्मचारी', 'PROFESSION', 100, 1),
('Retired Govt. Employee', 'सेवानिवृत्त कर्मचारी', 'PROFESSION', 110, 1),
('Ex-Serviceman', 'पूर्व सैनिक', 'PROFESSION', 120, 1),
('Police', 'पुलिस', 'PROFESSION', 130, 1),
('Businessman', 'व्यापारी', 'WORK', 140, 1),
('Shopkeeper', 'दुकानदार', 'WORK', 150, 1),
('Private Job', 'प्राइवेट नौकरी', 'WORK', 160, 1),
('Self-employed', 'स्वरोज़गार', 'WORK', 170, 1),
('Farmer', 'किसान', 'WORK', 180, 1),
('Labourer / Daily Wage', 'मज़दूर', 'WORK', 190, 1),
('Driver', 'चालक', 'WORK', 200, 1),
('Domestic Worker', 'घरेलू कामगार', 'WORK', 210, 1),
('Street Vendor', 'रेहड़ी-पटरी', 'WORK', 220, 1),
('Student', 'विद्यार्थी', 'OTHER', 230, 1),
('Homemaker', 'गृहिणी', 'OTHER', 240, 1),
('Unemployed', 'बेरोज़गार', 'OTHER', 250, 1),
('Senior Citizen', 'वरिष्ठ नागरिक', 'OTHER', 260, 1),
('Social Worker', 'सामाजिक कार्यकर्ता', 'OTHER', 270, 1),
('Self-Help Group Member', 'स्वयं सहायता समूह', 'OTHER', 280, 1);

INSERT IGNORE INTO political_statuses (name, name_local, leaning, sort_order, active) VALUES
('Strong Supporter', 'पक्का समर्थक', 'S', 10, 1),
('Supporter', 'समर्थक', 'S', 20, 1),
('Party Member', 'पार्टी सदस्य', 'S', 30, 1),
('Karyakarta', 'कार्यकर्ता', 'S', 40, 1),
('Leaning Towards Us', 'हमारी ओर झुकाव', 'S', 50, 1),
('Neutral / Undecided', 'तटस्थ / अनिश्चित', 'N', 60, 1),
('Swing Voter', 'स्विंग मतदाता', 'N', 70, 1),
('Not Interested in Politics', 'राजनीति में रुचि नहीं', 'N', 80, 1),
('Leaning Towards Opposition', 'विपक्ष की ओर झुकाव', 'O', 90, 1),
('Opposition Supporter', 'विपक्ष समर्थक', 'O', 100, 1),
('Opposition Worker', 'विपक्ष कार्यकर्ता', 'O', 110, 1),
('Not Met / Not Available', 'नहीं मिले', 'U', 120, 1);

INSERT IGNORE INTO org_statuses (name, name_local, level, sort_order, active) VALUES
('Panna Pramukh', 'पन्ना प्रमुख', 'BOOTH', 10, 1),
('Booth Committee Member', 'बूथ समिति सदस्य', 'BOOTH', 20, 1),
('Booth Agent', 'बूथ एजेंट', 'BOOTH', 30, 1),
('Booth President', 'बूथ अध्यक्ष', 'BOOTH', 40, 1),
('Shakti Kendra Pramukh', 'शक्ति केंद्र प्रमुख', 'WARD', 50, 1),
('Ward Committee Member', 'वार्ड समिति सदस्य', 'WARD', 60, 1),
('Mandal Office Bearer', 'मंडल पदाधिकारी', 'WARD', 70, 1),
('Mandal President', 'मंडल अध्यक्ष', 'WARD', 80, 1),
('District Office Bearer', 'जिला पदाधिकारी', 'DISTRICT', 90, 1),
('District President', 'जिला अध्यक्ष', 'DISTRICT', 100, 1),
('State Office Bearer', 'प्रदेश पदाधिकारी', 'DISTRICT', 110, 1),
('Mahila Morcha', 'महिला मोर्चा', 'FRONT', 120, 1),
('Yuva Morcha', 'युवा मोर्चा', 'FRONT', 130, 1),
('Kisan Morcha', 'किसान मोर्चा', 'FRONT', 140, 1),
('SC Morcha', 'अनुसूचित जाति मोर्चा', 'FRONT', 150, 1),
('OBC Morcha', 'पिछड़ा वर्ग मोर्चा', 'FRONT', 160, 1),
('Minority Morcha', 'अल्पसंख्यक मोर्चा', 'FRONT', 170, 1),
('IT / Social Media Cell', 'आईटी सेल', 'FRONT', 180, 1),
('Legal Cell', 'विधि प्रकोष्ठ', 'FRONT', 190, 1),
('Councillor', 'पार्षद', 'ELECTED', 200, 1),
('Former Councillor', 'पूर्व पार्षद', 'ELECTED', 210, 1),
('MLA / Former MLA', 'विधायक / पूर्व विधायक', 'ELECTED', 220, 1);

-- ---------------------------------------------------------------- Beneficiary / Help master
CREATE TABLE IF NOT EXISTS beneficiaries (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    name_local  VARCHAR(150),
    category    VARCHAR(20)           COMMENT 'HELP = help given by us, SCHEME = government scheme',
    sort_order  INT          NOT NULL DEFAULT 0,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_beneficiaries_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO beneficiaries (name, name_local, category, sort_order, active) VALUES
('Admission (school / college)', 'स्कूल / कॉलेज में दाखिला', 'HELP', 10, 1),
('Ration Card', 'राशन कार्ड', 'HELP', 20, 1),
('Ayushman Card', 'आयुष्मान कार्ड', 'HELP', 30, 1),
('Aadhaar Card', 'आधार कार्ड', 'HELP', 40, 1),
('Voter ID Card', 'वोटर कार्ड', 'HELP', 50, 1),
('Old Age Pension', 'वृद्धावस्था पेंशन', 'HELP', 60, 1),
('Widow Pension', 'विधवा पेंशन', 'HELP', 70, 1),
('Disability Pension', 'दिव्यांग पेंशन', 'HELP', 80, 1),
('Hospital Treatment', 'अस्पताल में इलाज', 'HELP', 90, 1),
('Job / Employment', 'नौकरी / रोज़गार', 'HELP', 100, 1),
('Birth / Death Certificate', 'जन्म / मृत्यु प्रमाण पत्र', 'HELP', 110, 1),
('Caste / Income Certificate', 'जाति / आय प्रमाण पत्र', 'HELP', 120, 1),
('Scholarship', 'छात्रवृत्ति', 'HELP', 130, 1),
('Bank Account', 'बैंक खाता', 'HELP', 140, 1),
('Electricity / Water Connection', 'बिजली / पानी कनेक्शन', 'HELP', 150, 1),
('Police / Legal Help', 'पुलिस / कानूनी मदद', 'HELP', 160, 1),
('PM Awas Yojana', 'प्रधानमंत्री आवास योजना', 'SCHEME', 170, 1),
('Ujjwala Gas', 'उज्ज्वला गैस', 'SCHEME', 180, 1),
('Free Ration', 'मुफ़्त राशन', 'SCHEME', 190, 1),
('PM Kisan', 'पीएम किसान', 'SCHEME', 200, 1),
('Jan Dhan Account', 'जन धन खाता', 'SCHEME', 210, 1),
('Mudra Loan', 'मुद्रा लोन', 'SCHEME', 220, 1),
('Ladli / Girl Child Scheme', 'लाडली योजना', 'SCHEME', 230, 1),
('Free Bus Travel (Women)', 'महिलाओं की मुफ़्त बस यात्रा', 'SCHEME', 240, 1),
('Free Electricity', 'मुफ़्त बिजली', 'SCHEME', 250, 1),
('Free Water', 'मुफ़्त पानी', 'SCHEME', 260, 1);

-- Roll update: a new voter roll compared with / applied to an assembly (values before the update kept for undo).
CREATE TABLE IF NOT EXISTS roll_updates (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assembly_no INT          NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    booths      TEXT                  COMMENT 'booth numbers in the uploaded roll, comma separated',
    status      VARCHAR(12)  NOT NULL DEFAULT 'PREVIEW' COMMENT 'PREVIEW, APPLIED, UNDONE, DISCARDED',
    counts      TEXT                  COMMENT 'JSON: rows per action',
    errors      TEXT                  COMMENT 'JSON: rows that could not be read',
    created_by  VARCHAR(50),
    created_at  DATETIME,
    applied_by  VARCHAR(50),
    applied_at  DATETIME,
    undone_at   DATETIME,
    INDEX idx_rollupd_ac (assembly_no, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roll_update_rows (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    roll_update_id BIGINT       NOT NULL,
    row_no         INT,
    action         VARCHAR(12)  NOT NULL COMMENT 'SAME, CHANGED, SHIFTED, NEW, RESTORED, NAMEMATCH, DELETED',
    voter_id       BIGINT,
    epic_no        VARCHAR(30),
    name           VARCHAR(150),
    booth_no       INT                   COMMENT 'booth in the new roll (old booth for DELETED)',
    serial_no      INT,
    old_booth_no   INT,
    old_serial_no  INT,
    has_survey     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'voter already has data entered by the party',
    data           TEXT                  COMMENT 'JSON: roll values from the new roll',
    changes        TEXT                  COMMENT 'JSON: field => [old, new]',
    `before`       TEXT                  COMMENT 'JSON: values before applying, for undo',
    INDEX idx_rollrow_action (roll_update_id, action),
    INDEX idx_rollrow_booth (roll_update_id, booth_no),
    INDEX idx_rollrow_voter (roll_update_id, voter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role-based access: users (app_users) <- user_roles -> roles <- role_permissions (permission codes).
-- Default roles ADMIN / OPERATOR / VIEWER are created by the application on first start.
CREATE TABLE IF NOT EXISTS roles (
    id           BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(40)   NOT NULL,
    name         VARCHAR(100)  NOT NULL,
    description  VARCHAR(300),
    system_role  BIT           NOT NULL DEFAULT 0,
    CONSTRAINT uk_role_code UNIQUE (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id     BIGINT       NOT NULL,
    permission  VARCHAR(30)  NOT NULL COMMENT 'e.g. VOTER_EDIT, REPORT_SENSITIVE, USER_ADMIN',
    CONSTRAINT uk_role_permission UNIQUE (role_id, permission),
    CONSTRAINT fk_permission_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_users (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL,
    password_hash  VARCHAR(100) NOT NULL COMMENT 'BCrypt',
    full_name      VARCHAR(100) NOT NULL,
    enabled        BIT          NOT NULL DEFAULT 1,
    assembly_no    INT                   COMMENT 'NULL = may log in to any assembly',
    scope_wards       VARCHAR(200)          COMMENT 'access area: ward numbers, e.g. "101, 102" (empty = all)',
    scope_booths      VARCHAR(500)          COMMENT 'access area: booth numbers, e.g. "3, 4"',
    scope_serial_from INT                   COMMENT 'access area: serial range inside one booth',
    scope_serial_to   INT,
    last_login     DATETIME(6),
    created_at     DATETIME(6),
    CONSTRAINT uk_user_username UNIQUE (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id  BIGINT NOT NULL,
    role_id  BIGINT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES app_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    at         DATETIME(6)  NOT NULL,
    username   VARCHAR(50),
    action     VARCHAR(30)  NOT NULL,
    entity     VARCHAR(30),
    entity_id  VARCHAR(50),
    details    VARCHAR(500),
    ip_address VARCHAR(45)           COMMENT 'IP address of the user',
    user_agent VARCHAR(255)          COMMENT 'Browser / device',
    INDEX idx_audit_time (at),
    INDEX idx_audit_ip (ip_address),
    INDEX idx_audit_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The first administrator is created by the application on first start
-- (app.security.admin.username / ADMIN_PASSWORD).

-- ---------------------------------------------------------------- SMS / WhatsApp
CREATE TABLE IF NOT EXISTS message_templates (
    id                    BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name                  VARCHAR(100)  NOT NULL,
    channel               VARCHAR(10)   NOT NULL COMMENT 'SMS / WHATSAPP',
    language              VARCHAR(10)   NOT NULL,
    body                  VARCHAR(1000) NOT NULL,
    provider_template_id  VARCHAR(100)           COMMENT 'DLT template id / WhatsApp template name / Twilio Content SID',
    params                VARCHAR(300)           COMMENT 'placeholders in provider variable order',
    active                BIT           NOT NULL DEFAULT 1,
    created_at            DATETIME(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_campaigns (
    id              BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)  NOT NULL,
    channel         VARCHAR(10)   NOT NULL,
    template_id     BIGINT        NOT NULL,
    filter_query    VARCHAR(1000),
    filter_summary  VARCHAR(500),
    status          VARCHAR(10)   NOT NULL,
    scheduled_at    DATETIME(6),
    total           INT           NOT NULL,
    sent            INT           NOT NULL,
    failed          INT           NOT NULL,
    skipped         INT           NOT NULL,
    created_by      VARCHAR(50),
    created_at      DATETIME(6),
    completed_at    DATETIME(6),
    CONSTRAINT fk_campaign_template FOREIGN KEY (template_id) REFERENCES message_templates (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_outbox (
    id            BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campaign_id   BIGINT        NOT NULL,
    voter_id      BIGINT,
    voter_name    VARCHAR(120),
    mobile        VARCHAR(16)   NOT NULL COMMENT 'E.164',
    body          VARCHAR(1000) NOT NULL,
    param_values  VARCHAR(1000),
    status        VARCHAR(10)   NOT NULL COMMENT 'QUEUED / SENDING / SENT / FAILED / SKIPPED',
    attempts      INT           NOT NULL,
    provider_id   VARCHAR(100),
    error         VARCHAR(300),
    created_at    DATETIME(6),
    sent_at       DATETIME(6),
    INDEX idx_outbox_status (status, campaign_id),
    INDEX idx_outbox_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_optouts (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    mobile      VARCHAR(10)  NOT NULL,
    channel     VARCHAR(10)           COMMENT 'NULL = every channel',
    reason      VARCHAR(200),
    created_by  VARCHAR(50),
    created_at  DATETIME(6),
    CONSTRAINT uk_optout UNIQUE (mobile, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- castes (master)
CREATE TABLE IF NOT EXISTS castes (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    name_local  VARCHAR(100),
    category    VARCHAR(10)           COMMENT 'GEN / OBC / SC / ST (optional)',
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_caste_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- sub castes (master)
CREATE TABLE IF NOT EXISTS sub_castes (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    caste_id    BIGINT       NOT NULL,
    name        VARCHAR(100) NOT NULL,
    name_local  VARCHAR(100),
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_sub_caste UNIQUE (caste_id, name),
    CONSTRAINT fk_sub_caste_caste FOREIGN KEY (caste_id) REFERENCES castes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
