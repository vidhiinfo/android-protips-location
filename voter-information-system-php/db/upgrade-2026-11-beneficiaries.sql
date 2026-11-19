-- =====================================================================
-- Beneficiary / Help master (help given to voters and government schemes) with starting values,
-- for a database created before this version. The application also adds it automatically. Safe to run again.
-- =====================================================================
SET NAMES utf8mb4;

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

