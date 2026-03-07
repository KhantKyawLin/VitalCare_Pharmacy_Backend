SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

INSERT INTO `categories` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Pain and Fever', '2025-10-02 10:07:29', '2025-10-02 10:07:29'),
(3, 'Anti Allergy', '2025-10-14 17:39:32', '2025-10-14 17:39:32'),
(4, 'Multivitamins & Supplements', '2025-10-14 17:44:21', '2025-10-14 17:44:21');

INSERT INTO `units` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Tablet', '2025-10-02 10:08:37', '2025-10-02 10:08:37'),
(2, 'Bottle', '2025-10-02 10:25:31', '2025-10-02 10:25:31'),
(3, 'Pack', '2025-10-14 17:34:41', '2025-10-14 17:34:41'),
(4, 'Box', '2025-10-14 17:37:56', '2025-10-14 17:37:56'),
(5, 'Sachet', '2025-10-14 17:47:46', '2025-10-14 17:47:46');

INSERT INTO `users` (`id`, `profile`, `name`, `email`, `password`, `role`, `gender`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(1, './image/defaultprofile.png', 'Khant Kyaw Lin', 'kkl@gmail.com', '$2y$10$zvBEPMhkB8zay2N2jFbUkuBuV5TOdOe5cU1lMwX5eVfEHyePwyTlO', 'admin', 'male', '09966454595', 'Insein', '2025-10-02 04:03:35', '2025-10-02 06:21:27'),
(2, 'uploads/profile/profile_2_68ea86f095248.jpg', 'Helium 007', 'helium@gmail.com', '$2y$10$DuiGF1343HK6clKjHSnxk.mULt0qPbgTJg1yy6/gm9lk/ndRIB/p.', 'user', 'male', '09972404097', 'Yangon', '2025-10-02 09:55:07', '2025-10-11 16:33:52'),
(3, 'uploads/profile/profile_3_68ee92bb28c44.jpg', 'Win Myat Mon', 'wmm@gmail.com', '$2y$10$3WS92LNqXxCNoTJRk2Fiv.YSfsjm2FBu9r1fdyGBE6Ak1Z9t0jIs.', 'admin', 'female', '09972404097', 'Insein', '2025-10-14 10:09:11', '2025-10-14 18:13:15');

INSERT INTO `products` (`id`, `name`, `category_id`, `description`, `usage`, `side_effects`, `dosage`, `unit_id`, `minimum_quantity`, `reorder_status`, `is_expired`, `price`, `created_at`, `updated_at`) VALUES
(1, 'Biogesic', 1, 'Pain reliever and fever reducer.', 'Take orally every 4-6 hours.', 'Nausea', '500 mg tablet', 1, 10, 0, 0, 2200.00, '2025-10-02 10:13:03', '2025-10-02 10:13:03'),
(2, 'Alaxan 10s', 1, 'Nonsteroidal anti-inflammatory', 'Take orally every 6-8 hours.', 'Stomach upset', '525 mg tablet', 1, 10, 0, 0, 3600.00, '2025-10-02 10:15:24', '2025-10-11 17:13:57'),
(3, 'Alaxan FR 10s', 1, 'Nonsteroidal anti-inflammatory', 'Take orally every 6-8 hours.', 'Stomach upset', '525 mg tablet', 1, 10, 1, 0, 4200.00, '2025-10-02 10:16:36', '2025-10-11 16:48:59'),
(4, 'Gofen 10s', 1, 'Nonsteroidal anti-inflammatory', 'Take orally every 6-8 hours.', 'Stomach upset', '200 mg tablet', 1, 10, 0, 0, 4800.00, '2025-10-02 10:17:42', '2025-10-02 10:17:42'),
(5, 'Paracap 10s', 1, 'Pain reliever and fever reducer.', 'Take orally every 4-6 hours.', 'Nausea', '500 mg tablet', 1, 10, 0, 0, 1800.00, '2025-10-02 10:18:52', '2025-10-02 10:18:52'),
(6, 'Diaflam 10s', 1, 'Used for muscle and joint pain.', 'Take orally every 6-8 hours.', 'Stomach upset', '50 mg tablet', 1, 10, 0, 0, 1800.00, '2025-10-02 10:20:59', '2025-10-02 10:20:59'),
(7, 'Decolgen 10s', 1, 'Pain reliever and fever reducer.', 'Take orally every 4-6 hours.', 'Stomach upset', '500 mg tablet', 1, 10, 0, 0, 5000.00, '2025-10-02 10:22:16', '2025-10-02 10:22:16'),
(8, 'Decolgen Extra 10s', 1, 'Pain reliever and fever reducer.', 'Take orally every 4-6 hours.', 'Stomach upset', '500 mg tablet', 1, 10, 0, 0, 5400.00, '2025-10-02 10:23:19', '2025-10-02 10:23:19'),
(10, 'Biogesic 120mg', 1, 'Pain reliever and fever reducer.', 'Take orally every 4-6 hours.', 'Nausea', '120mg/5ml', 1, 10, 1, 0, 4800.00, '2025-10-02 10:26:38', '2025-10-11 03:55:31'),
(11, 'Biogesic 250mg', 1, 'Pain reliever and fever reducer.', 'Take orally every 4-6 hours.', 'Nausea', '250mg/5ml', 2, 10, 0, 0, 4800.00, '2025-10-02 10:28:14', '2025-10-02 10:28:14'),
(13, 'Lensen (Syrup)', 1, 'Pain reliever and fever reducer.', 'Take orally every 4-6 hours.', 'Nausea', '250mg/5ml', 2, 10, 1, 0, 4800.00, '2025-10-14 17:32:45', '2025-10-14 18:01:01'),
(14, 'Fluza 4s', 1, 'Pain reliever and fever reducer.', 'Take orally every 4-6 hours.', 'Stomach upset', '500 mg tablet', 1, 10, 0, 0, 1200.00, '2025-10-14 17:34:04', '2025-10-14 17:34:04'),
(15, 'Tiger Balm', 1, 'An effective remedy for stiff neck.', 'Take every 6-8 hours as needed.', 'Blistering', '7 x 10 cm (1 pouch)', 3, 10, 1, 0, 6000.00, '2025-10-14 17:35:56', '2025-10-14 18:01:01');

INSERT INTO `pictures` (`id`, `product_id`, `image_path`, `is_primary`, `created_at`, `updated_at`) VALUES
(1, 1, 'products/product_1_68de502f0cfce.jpg', 1, '2025-10-02 10:13:03', '2025-10-02 10:13:03'),
(2, 1, 'products/product_1_68de502f0df34.jpg', 0, '2025-10-02 10:13:03', '2025-10-02 10:13:03'),
(3, 2, 'products/product_2_68de50bce6730.jpg', 1, '2025-10-02 10:15:24', '2025-10-02 10:15:24'),
(4, 2, 'products/product_2_68de50bce7477.jpg', 0, '2025-10-02 10:15:24', '2025-10-02 10:15:24'),
(5, 3, 'products/product_3_68de510406073.jpg', 1, '2025-10-02 10:16:36', '2025-10-02 10:16:36'),
(7, 4, 'products/product_4_68de514634d66.jpg', 1, '2025-10-02 10:17:42', '2025-10-02 10:17:42'),
(9, 5, 'products/product_5_68de518c9f074.jpg', 1, '2025-10-02 10:18:52', '2025-10-02 10:18:52'),
(11, 6, 'products/product_6_68de520b2f246.jpg', 1, '2025-10-02 10:20:59', '2025-10-02 10:20:59'),
(13, 7, 'products/product_7_68de5258565ff.jpg', 1, '2025-10-02 10:22:16', '2025-10-02 10:22:16'),
(14, 8, 'products/product_8_68de5297893b0.jpg', 1, '2025-10-02 10:23:19', '2025-10-02 10:23:19'),
(16, 10, 'products/product_10_68de535ed086d.png', 1, '2025-10-02 10:26:38', '2025-10-02 10:26:38'),
(17, 11, 'products/product_11_68de53bedeaf0.jpg', 1, '2025-10-02 10:28:14', '2025-10-02 10:28:14'),
(20, 13, 'products/product_13_68ee893d8a584.jpg', 1, '2025-10-14 17:32:45', '2025-10-14 17:32:45'),
(21, 14, 'products/product_14_68ee898c5a258.jpg', 1, '2025-10-14 17:34:04', '2025-10-14 17:34:04'),
(22, 15, 'products/product_15_68ee89fcbcffe.jpg', 1, '2025-10-14 17:35:56', '2025-10-14 17:35:56');

COMMIT;
