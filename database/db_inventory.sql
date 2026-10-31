-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2026 at 02:53 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventaris_bulk`
--

CREATE TABLE `inventaris_bulk` (
  `barang_id` int(11) NOT NULL,
  `jumlah_stok` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventaris_bulk`
--

INSERT INTO `inventaris_bulk` (`barang_id`, `jumlah_stok`) VALUES
(13, 900.00),
(14, 1000.00),
(15, 10.00);

-- --------------------------------------------------------

--
-- Table structure for table `inventaris_serial`
--

CREATE TABLE `inventaris_serial` (
  `id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `serial_number` varchar(255) NOT NULL,
  `status` enum('GUDANG','TERPASANG','RUSAK','RETUR') DEFAULT 'GUDANG',
  `tanggal_masuk` date NOT NULL,
  `pemasangan_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventaris_serial`
--

INSERT INTO `inventaris_serial` (`id`, `barang_id`, `serial_number`, `status`, `tanggal_masuk`, `pemasangan_id`) VALUES
(16, 12, 'ZTEEQG2JCC14626', 'TERPASANG', '2026-06-22', 19),
(17, 12, 'ZTEEOJK3421', 'TERPASANG', '2026-06-22', 20),
(18, 12, 'ZTEEY2JJ1867', 'TERPASANG', '2026-06-22', 21),
(19, 12, 'ZTEETGD5KY67845', 'TERPASANG', '2026-06-22', 23),
(20, 12, 'ZTEEGH7GD8757', 'TERPASANG', '2026-06-22', 22);

-- --------------------------------------------------------

--
-- Table structure for table `katalog_barang`
--

CREATE TABLE `katalog_barang` (
  `id` int(11) NOT NULL,
  `nama_barang` varchar(255) NOT NULL,
  `kategori` varchar(100) NOT NULL,
  `satuan_hitung` varchar(50) NOT NULL,
  `is_serialized` enum('Y','N') DEFAULT 'N',
  `min_stok` int(11) DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `katalog_barang`
--

INSERT INTO `katalog_barang` (`id`, `nama_barang`, `kategori`, `satuan_hitung`, `is_serialized`, `min_stok`) VALUES
(12, 'ONT ZTE FiberHome', 'Perangkat', 'Unit', 'Y', 5),
(13, 'Kabel FO', 'Material', 'Meter', 'N', 100),
(14, 'Kabel FO', 'Material', 'Meter', 'N', 1),
(15, 'Klem', 'Material', 'Pack', 'N', 2);

-- --------------------------------------------------------

--
-- Table structure for table `log_stok_manual`
--

CREATE TABLE `log_stok_manual` (
  `id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jenis_update` enum('masuk','keluar') NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `catatan` text NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `odp`
--

CREATE TABLE `odp` (
  `id` int(11) NOT NULL,
  `kode_odp` varchar(50) NOT NULL,
  `nama_odp` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `link_maps` varchar(1000) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `kapasitas_port` int(10) UNSIGNED NOT NULL DEFAULT 8,
  `status` enum('Aktif','Nonaktif','Maintenance') NOT NULL DEFAULT 'Aktif',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `odp`
--

INSERT INTO `odp` (`id`, `kode_odp`, `nama_odp`, `alamat`, `link_maps`, `latitude`, `longitude`, `kapasitas_port`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'ODP-001', 'ODP Pusat', 'https://maps.app.goo.gl/XiVPErCt6tiy7W6R9', NULL, -7.4188787, 109.3675665, 8, 'Aktif', '', '2026-10-14 02:07:46', '2026-10-14 13:37:33'),
(2, 'ODP-002', 'ODP 2', 'toyareka', 'https://maps.app.goo.gl/RAk5m42dw2tq7hRy6', -7.4208861, 109.3667942, 8, 'Aktif', '', '2026-10-14 02:54:41', '2026-10-15 01:14:31');

-- --------------------------------------------------------

--
-- Table structure for table `odp_port`
--

CREATE TABLE `odp_port` (
  `id` int(11) NOT NULL,
  `odp_id` int(11) NOT NULL,
  `nomor_port` int(10) UNSIGNED NOT NULL,
  `pemasangan_id` int(11) DEFAULT NULL,
  `status` enum('Aktif','Rusak','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `odp_port`
--

INSERT INTO `odp_port` (`id`, `odp_id`, `nomor_port`, `pemasangan_id`, `status`, `catatan`, `created_at`) VALUES
(1, 1, 1, 22, 'Aktif', '', '2026-10-14 02:13:21'),
(2, 1, 2, 21, 'Aktif', '', '2026-10-14 02:13:21'),
(3, 1, 3, 20, 'Aktif', '', '2026-10-14 02:13:21'),
(4, 1, 4, 19, 'Aktif', '', '2026-10-14 02:13:21'),
(5, 1, 5, NULL, 'Aktif', NULL, '2026-10-14 02:13:21'),
(6, 1, 6, NULL, 'Aktif', NULL, '2026-10-14 02:13:21'),
(7, 1, 7, NULL, 'Aktif', NULL, '2026-10-14 02:13:21'),
(8, 1, 8, NULL, 'Aktif', NULL, '2026-10-14 02:13:21'),
(57, 2, 1, 23, 'Aktif', NULL, '2026-10-14 12:41:23'),
(58, 2, 2, NULL, 'Aktif', NULL, '2026-10-14 12:41:23'),
(59, 2, 3, NULL, 'Aktif', NULL, '2026-10-14 12:41:23'),
(60, 2, 4, NULL, 'Aktif', NULL, '2026-10-14 12:41:23'),
(61, 2, 5, NULL, 'Aktif', NULL, '2026-10-14 12:41:23'),
(62, 2, 6, NULL, 'Aktif', NULL, '2026-10-14 12:41:23'),
(63, 2, 7, NULL, 'Aktif', NULL, '2026-10-14 12:41:23'),
(64, 2, 8, NULL, 'Aktif', NULL, '2026-10-14 12:41:23');

-- --------------------------------------------------------

--
-- Table structure for table `pemasangan`
--

CREATE TABLE `pemasangan` (
  `id` int(11) NOT NULL,
  `pelanggan_nama` varchar(255) NOT NULL,
  `alamat` text NOT NULL,
  `link_maps` varchar(1000) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `tanggal_pasang` date NOT NULL,
  `langganan` varchar(255) DEFAULT NULL,
  `foto_lokasi` varchar(255) DEFAULT NULL,
  `waktu_input` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pemasangan`
--

INSERT INTO `pemasangan` (`id`, `pelanggan_nama`, `alamat`, `link_maps`, `latitude`, `longitude`, `tanggal_pasang`, `langganan`, `foto_lokasi`, `waktu_input`) VALUES
(19, 'Safanah Nurfitri', 'Toyareka', '', NULL, NULL, '2026-06-06', 'IKonek Speed 30 Mbps', '1782128845_6a3920cde2864.jpeg', '2026-06-22 11:47:25'),
(20, 'Kuswadi Wagimin', 'Toyareka', '', NULL, NULL, '2026-06-07', 'IKonek Speed 30 Mbps', NULL, '2026-06-22 11:48:31'),
(21, 'Desy Hardiyati', 'Toyareka', '', NULL, NULL, '2026-06-20', 'Promo Paralel Eco 5 Mbps', '1782128985_6a3921599d30e.jpeg', '2026-06-22 11:49:45'),
(22, 'ahmad', 'muntang', '', -7.4247950, 109.3657870, '2026-10-01', 'IKonek Speed 15 Mbps', '1791515535_6ac85b8f02033.jpg', '2026-10-09 03:12:15'),
(23, 'anggi', 'toyareka', 'https://maps.app.goo.gl/wxa6Ye3BaF6CQ6en8', -7.4207589, 109.3666826, '2026-10-14', 'IKonek Speed 50 Mbps', '1791981709_6acf788d2242e.jpg', '2026-10-14 12:41:49');

-- --------------------------------------------------------

--
-- Table structure for table `pemasangan_detail`
--

CREATE TABLE `pemasangan_detail` (
  `id` int(11) NOT NULL,
  `pemasangan_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `jumlah_terpakai` decimal(10,2) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pemasangan_detail`
--

INSERT INTO `pemasangan_detail` (`id`, `pemasangan_id`, `barang_id`, `serial_number`, `jumlah_terpakai`, `keterangan`) VALUES
(19, 19, 12, 'ZTEEQG2JCC14626', 1.00, ''),
(20, 19, 13, NULL, 20.00, ''),
(21, 20, 12, 'ZTEEOJK3421', 1.00, ''),
(22, 20, 13, NULL, 30.00, ''),
(23, 21, 12, 'ZTEEY2JJ1867', 1.00, ''),
(24, 21, 13, NULL, 50.00, ''),
(25, 22, 12, 'ZTEEGH7GD8757', 1.00, ''),
(26, 23, 12, 'ZTEETGD5KY67845', 1.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','admin_biasa') NOT NULL DEFAULT 'admin_biasa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`) VALUES
(1, 'teknisi', 'teknisi123', NULL, 'admin_biasa'),
(2, 'admin', 'superadmin', NULL, 'superadmin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventaris_bulk`
--
ALTER TABLE `inventaris_bulk`
  ADD PRIMARY KEY (`barang_id`);

--
-- Indexes for table `inventaris_serial`
--
ALTER TABLE `inventaris_serial`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `pemasangan_id` (`pemasangan_id`);

--
-- Indexes for table `katalog_barang`
--
ALTER TABLE `katalog_barang`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `log_stok_manual`
--
ALTER TABLE `log_stok_manual`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `odp`
--
ALTER TABLE `odp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_odp_kode` (`kode_odp`);

--
-- Indexes for table `odp_port`
--
ALTER TABLE `odp_port`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_odp_nomor_port` (`odp_id`,`nomor_port`),
  ADD UNIQUE KEY `uq_port_pemasangan` (`pemasangan_id`);

--
-- Indexes for table `pemasangan`
--
ALTER TABLE `pemasangan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pemasangan_detail`
--
ALTER TABLE `pemasangan_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pemasangan_id` (`pemasangan_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventaris_serial`
--
ALTER TABLE `inventaris_serial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `katalog_barang`
--
ALTER TABLE `katalog_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `log_stok_manual`
--
ALTER TABLE `log_stok_manual`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `odp`
--
ALTER TABLE `odp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `odp_port`
--
ALTER TABLE `odp_port`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `pemasangan`
--
ALTER TABLE `pemasangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `pemasangan_detail`
--
ALTER TABLE `pemasangan_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventaris_bulk`
--
ALTER TABLE `inventaris_bulk`
  ADD CONSTRAINT `inventaris_bulk_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `katalog_barang` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventaris_serial`
--
ALTER TABLE `inventaris_serial`
  ADD CONSTRAINT `inventaris_serial_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `katalog_barang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventaris_serial_ibfk_2` FOREIGN KEY (`pemasangan_id`) REFERENCES `pemasangan` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `log_stok_manual`
--
ALTER TABLE `log_stok_manual`
  ADD CONSTRAINT `log_stok_manual_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `katalog_barang` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `odp_port`
--
ALTER TABLE `odp_port`
  ADD CONSTRAINT `fk_odp_port_odp` FOREIGN KEY (`odp_id`) REFERENCES `odp` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_odp_port_pemasangan` FOREIGN KEY (`pemasangan_id`) REFERENCES `pemasangan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `pemasangan_detail`
--
ALTER TABLE `pemasangan_detail`
  ADD CONSTRAINT `pemasangan_detail_ibfk_1` FOREIGN KEY (`pemasangan_id`) REFERENCES `pemasangan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pemasangan_detail_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `katalog_barang` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
