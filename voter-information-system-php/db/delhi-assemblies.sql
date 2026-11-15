-- =====================================================================
-- Delhi: the 70 assembly constituencies (Delimitation 2008), English + Hindi names,
-- 12 seats reserved for SC. They fill Masters > Assemblies and the Assembly
-- drop-down on the login page.
--
-- RUN: phpMyAdmin > select your database (e.g. electwua_newvis) > Import (or SQL tab) > Go
-- Safe to run more than once (existing numbers are updated, not duplicated).
-- =====================================================================
SET NAMES utf8mb4;

-- 1. Show ONLY Delhi in the drop-down: remove other assemblies from the master list
--    (only those that have no voters, so no data is lost)
DELETE FROM assemblies
 WHERE (state IS NULL OR state <> 'Delhi')
   AND assembly_no NOT IN (SELECT DISTINCT assembly_no FROM voters);

-- 2. Add / update the 70 Delhi constituencies
INSERT INTO assemblies (assembly_no, name, name_local, district, state, reserved_for) VALUES
(1, 'Narela', 'नरेला', NULL, 'Delhi', 'GEN'),
(2, 'Burari', 'बुराड़ी', NULL, 'Delhi', 'GEN'),
(3, 'Timarpur', 'तिमारपुर', NULL, 'Delhi', 'GEN'),
(4, 'Adarsh Nagar', 'आदर्श नगर', NULL, 'Delhi', 'GEN'),
(5, 'Badli', 'बादली', NULL, 'Delhi', 'GEN'),
(6, 'Rithala', 'रिठाला', NULL, 'Delhi', 'GEN'),
(7, 'Bawana', 'बवाना', NULL, 'Delhi', 'SC'),
(8, 'Mundka', 'मुंडका', NULL, 'Delhi', 'GEN'),
(9, 'Kirari', 'किराड़ी', NULL, 'Delhi', 'GEN'),
(10, 'Sultanpur Majra', 'सुल्तानपुर माजरा', NULL, 'Delhi', 'SC'),
(11, 'Nangloi Jat', 'नांगलोई जाट', NULL, 'Delhi', 'GEN'),
(12, 'Mangol Puri', 'मंगोलपुरी', NULL, 'Delhi', 'SC'),
(13, 'Rohini', 'रोहिणी', NULL, 'Delhi', 'GEN'),
(14, 'Shalimar Bagh', 'शालीमार बाग', NULL, 'Delhi', 'GEN'),
(15, 'Shakur Basti', 'शकूर बस्ती', NULL, 'Delhi', 'GEN'),
(16, 'Tri Nagar', 'त्रिनगर', NULL, 'Delhi', 'GEN'),
(17, 'Wazirpur', 'वज़ीरपुर', NULL, 'Delhi', 'GEN'),
(18, 'Model Town', 'मॉडल टाउन', NULL, 'Delhi', 'GEN'),
(19, 'Sadar Bazar', 'सदर बाज़ार', NULL, 'Delhi', 'GEN'),
(20, 'Chandni Chowk', 'चांदनी चौक', NULL, 'Delhi', 'GEN'),
(21, 'Matia Mahal', 'मटिया महल', NULL, 'Delhi', 'GEN'),
(22, 'Ballimaran', 'बल्लीमारान', NULL, 'Delhi', 'GEN'),
(23, 'Karol Bagh', 'करोल बाग', NULL, 'Delhi', 'SC'),
(24, 'Patel Nagar', 'पटेल नगर', NULL, 'Delhi', 'SC'),
(25, 'Moti Nagar', 'मोती नगर', NULL, 'Delhi', 'GEN'),
(26, 'Madipur', 'मादीपुर', NULL, 'Delhi', 'SC'),
(27, 'Rajouri Garden', 'राजौरी गार्डन', NULL, 'Delhi', 'GEN'),
(28, 'Hari Nagar', 'हरि नगर', NULL, 'Delhi', 'GEN'),
(29, 'Tilak Nagar', 'तिलक नगर', NULL, 'Delhi', 'GEN'),
(30, 'Janakpuri', 'जनकपुरी', NULL, 'Delhi', 'GEN'),
(31, 'Vikaspuri', 'विकासपुरी', NULL, 'Delhi', 'GEN'),
(32, 'Uttam Nagar', 'उत्तम नगर', NULL, 'Delhi', 'GEN'),
(33, 'Dwarka', 'द्वारका', NULL, 'Delhi', 'GEN'),
(34, 'Matiala', 'मटियाला', NULL, 'Delhi', 'GEN'),
(35, 'Najafgarh', 'नजफगढ़', NULL, 'Delhi', 'GEN'),
(36, 'Bijwasan', 'बिजवासन', NULL, 'Delhi', 'GEN'),
(37, 'Palam', 'पालम', NULL, 'Delhi', 'GEN'),
(38, 'Delhi Cantt', 'दिल्ली कैंट', NULL, 'Delhi', 'GEN'),
(39, 'Rajinder Nagar', 'राजेंद्र नगर', NULL, 'Delhi', 'GEN'),
(40, 'New Delhi', 'नई दिल्ली', NULL, 'Delhi', 'GEN'),
(41, 'Jangpura', 'जंगपुरा', NULL, 'Delhi', 'GEN'),
(42, 'Kasturba Nagar', 'कस्तूरबा नगर', NULL, 'Delhi', 'GEN'),
(43, 'Malviya Nagar', 'मालवीय नगर', NULL, 'Delhi', 'GEN'),
(44, 'R K Puram', 'आर. के. पुरम', NULL, 'Delhi', 'GEN'),
(45, 'Mehrauli', 'महरौली', NULL, 'Delhi', 'GEN'),
(46, 'Chhatarpur', 'छतरपुर', NULL, 'Delhi', 'GEN'),
(47, 'Deoli', 'देवली', NULL, 'Delhi', 'SC'),
(48, 'Ambedkar Nagar', 'अंबेडकर नगर', NULL, 'Delhi', 'SC'),
(49, 'Sangam Vihar', 'संगम विहार', NULL, 'Delhi', 'GEN'),
(50, 'Greater Kailash', 'ग्रेटर कैलाश', NULL, 'Delhi', 'GEN'),
(51, 'Kalkaji', 'कालकाजी', NULL, 'Delhi', 'GEN'),
(52, 'Tughlakabad', 'तुगलकाबाद', NULL, 'Delhi', 'GEN'),
(53, 'Badarpur', 'बदरपुर', NULL, 'Delhi', 'GEN'),
(54, 'Okhla', 'ओखला', NULL, 'Delhi', 'GEN'),
(55, 'Trilokpuri', 'त्रिलोकपुरी', NULL, 'Delhi', 'SC'),
(56, 'Kondli', 'कोंडली', NULL, 'Delhi', 'SC'),
(57, 'Patparganj', 'पटपड़गंज', NULL, 'Delhi', 'GEN'),
(58, 'Laxmi Nagar', 'लक्ष्मी नगर', NULL, 'Delhi', 'GEN'),
(59, 'Vishwas Nagar', 'विश्वास नगर', NULL, 'Delhi', 'GEN'),
(60, 'Krishna Nagar', 'कृष्णा नगर', NULL, 'Delhi', 'GEN'),
(61, 'Gandhi Nagar', 'गांधी नगर', NULL, 'Delhi', 'GEN'),
(62, 'Shahdara', 'शाहदरा', NULL, 'Delhi', 'GEN'),
(63, 'Seemapuri', 'सीमापुरी', NULL, 'Delhi', 'SC'),
(64, 'Rohtas Nagar', 'रोहतास नगर', NULL, 'Delhi', 'GEN'),
(65, 'Seelampur', 'सीलमपुर', NULL, 'Delhi', 'GEN'),
(66, 'Ghonda', 'घोंडा', NULL, 'Delhi', 'GEN'),
(67, 'Babarpur', 'बाबरपुर', NULL, 'Delhi', 'GEN'),
(68, 'Gokalpur', 'गोकलपुर', NULL, 'Delhi', 'SC'),
(69, 'Mustafabad', 'मुस्तफाबाद', NULL, 'Delhi', 'GEN'),
(70, 'Karawal Nagar', 'करावल नगर', NULL, 'Delhi', 'GEN')
ON DUPLICATE KEY UPDATE name = VALUES(name), name_local = VALUES(name_local), state = VALUES(state), reserved_for = VALUES(reserved_for);

-- 3. OPTIONAL - only if you imported voter_db_demo.sql and want to remove ALL demo data
--    (remove the "-- " in front of the lines below, then run again):
-- DELETE FROM booth_results; DELETE FROM candidates; DELETE FROM elections; DELETE FROM parties;
-- DELETE FROM booths; DELETE FROM message_outbox; DELETE FROM message_campaigns;
-- DELETE FROM voters WHERE epic_no LIKE 'DEM%' OR epic_no LIKE 'DUP%';
-- DELETE FROM assemblies WHERE state IS NULL OR state <> 'Delhi';
