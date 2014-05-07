--
-- Table structure for table `hotarticles`
--

DROP TABLE IF EXISTS `hotarticles`;
CREATE TABLE `hotarticles` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `method` enum('category','template') DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `article_number` smallint(5) NOT NULL DEFAULT '5',
  `span_days` smallint(5) NOT NULL DEFAULT '3',
  `target_page` varchar(255) DEFAULT NULL,
  `orange` smallint(5) NOT NULL DEFAULT '10',
  `red` smallint(5) NOT NULL DEFAULT '20',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
