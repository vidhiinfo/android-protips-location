-- =====================================================================
-- Muslim caste and its sub castes (biradaris) common in Delhi / North India, English + Hindi.
-- Category is left empty: some sub castes are OBC in the Delhi / Central lists and others are General;
-- check the current official lists before relying on a category.
-- Import in phpMyAdmin (select your database > Import). Safe to run more than once:
-- existing entries with the same name are left as they are.
-- For survey entry only: record what the voter or family tells the worker. Never guess caste or
-- religion from names; appeals on caste or religion are prohibited by law.
-- =====================================================================
SET NAMES utf8mb4;

INSERT IGNORE INTO castes (name, name_local, category, active) VALUES ('Muslim', 'मुस्लिम', NULL, 1);
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Sheikh', 'शेख', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Syed', 'सैयद', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Pathan', 'पठान', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Mughal', 'मुग़ल', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Siddiqui', 'सिद्दीकी', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Malik', 'मलिक', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Alvi', 'अल्वी', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Ansari (Julaha / Momin)', 'अंसारी (जुलाहा / मोमिन)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Qureshi (Kasai / Qassab)', 'क़ुरैशी (कसाई / क़स्साब)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Saifi (Lohar)', 'सैफ़ी (लोहार)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Salmani (Nai / Hajjam)', 'सलमानी (नाई / हज्जाम)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Mansoori (Dhuniya / Behna)', 'मंसूरी (धुनिया / बेहना)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Idrisi (Darzi)', 'इदरीसी (दर्ज़ी)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Abbasi (Bhishti / Saqqa)', 'अब्बासी (भिश्ती / सक़्क़ा)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Rangrez', 'रंगरेज़', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Raeen / Kunjra', 'राईन / कुंजड़ा', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Teli', 'तेली', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Manihar', 'मनिहार', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Ghosi', 'घोसी', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Gaddi', 'गद्दी', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Mewati / Meo', 'मेवाती / मेव', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Faqir / Shah', 'फ़क़ीर / शाह', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Mirasi', 'मिरासी', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Bhatiyara', 'भटियारा', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Chikwa', 'चिकवा', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Dhobi (Muslim)', 'धोबी (मुस्लिम)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Halwai (Muslim)', 'हलवाई (मुस्लिम)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Nat (Muslim)', 'नट (मुस्लिम)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Banjara (Muslim)', 'बंजारा (मुस्लिम)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Rajput (Muslim)', 'राजपूत (मुस्लिम)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Tyagi (Muslim)', 'त्यागी (मुस्लिम)', 1 FROM castes WHERE name = 'Muslim';
INSERT IGNORE INTO sub_castes (caste_id, name, name_local, active) SELECT id, 'Other Muslim', 'अन्य मुस्लिम', 1 FROM castes WHERE name = 'Muslim';
