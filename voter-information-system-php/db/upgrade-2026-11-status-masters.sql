-- =====================================================================
-- Social / Political / Organisational Status masters (drop-down lists) with their starting values,
-- for a database created before this version. The application also adds these automatically on the
-- first page load. Safe to run more than once: existing values are kept, missing ones are added.
-- Edit the lists afterwards in Masters > Social Status / Political Status / Organisational Status.
-- =====================================================================
SET NAMES utf8mb4;

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
