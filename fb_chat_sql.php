CREATE TABLE fb_chat (
  `fb_chat_id` int(10) NOT NULL AUTO_INCREMENT,
  `fb_chat_from` int(10) NOT NULL,
  `fb_chat_to` int(10) NOT NULL,
  `fb_chat_msg` varchar(255) NOT NULL,
  `fb_chat_sent` int(10) NOT NULL,
  `fb_chat_rcd` int(10) NOT NULL,
  PRIMARY KEY (`fb_chat_id`)
) ENGINE=InnoDB;

CREATE TABLE fb_chat_block (
  `fb_chat_block_id` int(10) NOT NULL AUTO_INCREMENT,
  `fb_chat_block_uid1` int(10) NOT NULL,
  `fb_chat_block_uid2` int(10) NOT NULL,
  `fb_chat_block_time` int(10) NOT NULL,
  PRIMARY KEY (`fb_chat_block_id`)
) ENGINE=MyISAM;