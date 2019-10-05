/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dm_inmate_population` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date DEFAULT NULL,
  `population` int(10) unsigned DEFAULT NULL,
  `admitted_date` date DEFAULT NULL,
  `sex` varchar(45) DEFAULT NULL,
  `race` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inmate_cases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `common_pleas` varchar(45) DEFAULT NULL,
  `municipal` varchar(45) DEFAULT NULL,
  `other` varchar(45) DEFAULT NULL,
  `court_date` datetime DEFAULT NULL,
  `orc_code` varchar(45) DEFAULT NULL,
  `description` varchar(45) DEFAULT NULL,
  `bond_type` varchar(45) DEFAULT NULL,
  `bond_amount` varchar(45) DEFAULT NULL,
  `disposition` varchar(45) DEFAULT NULL,
  `fine` varchar(45) DEFAULT NULL,
  `comments` varchar(255) DEFAULT NULL,
  `projected_release` datetime DEFAULT NULL,
  `holder` varchar(45) DEFAULT NULL,
  `inmate_id` varchar(45) DEFAULT NULL,
  `date_created` datetime DEFAULT NULL,
  `date_modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQUE` (`common_pleas`,`municipal`,`other`,`orc_code`,`inmate_id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inmate_information` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_created` datetime DEFAULT NULL,
  `date_modified` datetime DEFAULT NULL,
  `last_name` varchar(45) DEFAULT NULL,
  `first_name` varchar(45) DEFAULT NULL,
  `jms_number` varchar(45) DEFAULT NULL,
  `control_number` varchar(45) DEFAULT NULL,
  `sex` varchar(45) DEFAULT NULL,
  `admitted_date` datetime DEFAULT NULL,
  `race` varchar(45) DEFAULT NULL,
  `housing_location` varchar(45) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `dob` varchar(45) DEFAULT NULL,
  `image` longtext,
  `active` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQUE` (`jms_number`,`control_number`,`sex`,`admitted_date`,`race`,`fullname`,`dob`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
