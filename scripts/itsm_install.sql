SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `itsm_core_category` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_status` (
  `id` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  `ready` int(1) NOT NULL,
  `closed` int(1) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_core_subcategory` (
  `id` int(11) NOT NULL,
  `parent` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_buildings` (
  `id` int(11) NOT NULL,
  `customer` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `postalcode` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `idvp` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_customers` (
  `id` int(11) NOT NULL,
  `din` varchar(6) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `primarybuilding` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `postalcode` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `primaryemail` varchar(255) DEFAULT NULL,
  `primaryphone` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_operatorgroups` (
  `id` int(11) NOT NULL,
  `groupname` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_operators` (
  `id` int(11) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `allowlogin` int(1) NOT NULL,
  `firstlineincidents` int(1) NOT NULL,
  `secondlineincidents` int(1) NOT NULL,
  `reqforchange` int(1) NOT NULL,
  `simplechange` int(1) NOT NULL,
  `extchange` int(1) NOT NULL,
  `problems` int(1) NOT NULL,
  `operations` int(1) NOT NULL,
  `assets` int(1) NOT NULL,
  `persons` int(1) NOT NULL,
  `operators` int(1) NOT NULL,
  `buildings` int(1) NOT NULL,
  `customers` int(1) NOT NULL,
  `suppliers` int(1) NOT NULL,
  `groups` int(1) NOT NULL,
  `events` int(1) NOT NULL,
  `ubm` int(1) NOT NULL,
  `reporting` int(1) NOT NULL,
  `isadmin` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_opgrouplinks` (
  `id` int(11) NOT NULL,
  `groupid` int(11) NOT NULL,
  `operatorid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_persongrouplinks` (
  `id` int(11) NOT NULL,
  `person` int(11) NOT NULL,
  `persongroup` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_persongroups` (
  `id` int(11) NOT NULL,
  `groupname` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_persons` (
  `id` int(11) NOT NULL,
  `customerid` int(11) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `allowssp` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `itsm_ob_suppliers` (
  `id` int(11) NOT NULL,
  `cin` varchar(6) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `postalcode` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `primaryemail` varchar(255) DEFAULT NULL,
  `primaryphone` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


ALTER TABLE `itsm_core_category`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_core_status`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_core_subcategory`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_buildings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_customers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_operatorgroups`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_operators`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_opgrouplinks`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_persongrouplinks`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_persongroups`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_persons`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `itsm_ob_suppliers`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `itsm_core_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_core_subcategory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_operatorgroups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_operators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_opgrouplinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_persongrouplinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_persongroups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_persons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `itsm_ob_suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
