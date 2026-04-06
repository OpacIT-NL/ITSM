CREATE TABLE `itsm_km_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `publicaccess` int(1) NOT NULL DEFAULT 1,
  `createdby` int(11) NOT NULL,
  `createdat` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parentid` (`parentid`),
  KEY `createdby` (`createdby`),
  CONSTRAINT `itsm_km_items_ibfk_1` FOREIGN KEY (`parentid`) REFERENCES `itsm_km_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itsm_km_items_ibfk_2` FOREIGN KEY (`createdby`) REFERENCES `itsm_ob_operators` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;