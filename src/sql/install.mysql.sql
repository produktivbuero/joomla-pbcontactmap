# install.mysql.utf8.sql

CREATE TABLE `#__contact_pbcontactmap` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `query` mediumtext NOT NULL COMMENT 'Query made towards OSM',
  `place_id` int(11) NOT NULL COMMENT 'OSM place id',
  `licence` mediumtext NOT NULL,
  `osm_type` varchar(100) NOT NULL,
  `osm_id` int(11) NOT NULL,
  `boundingbox` mediumtext NOT NULL,
  `lat` float(10,8) NOT NULL,
  `lon` float(11,8) NOT NULL,
  `name` varchar(100) NOT NULL,
  `display_name` mediumtext NOT NULL,
  `class` varchar(100) NOT NULL,
  `type` varchar(100) NOT NULL,
  `place_rank` int(11) NOT NULL,
  `importance` float(5,4) NOT NULL,
  `addresstype` varchar(100) NOT NULL
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;