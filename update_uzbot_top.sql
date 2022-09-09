ALTER TABLE wcf1_uzbot_top ADD blogEntry INT(10) DEFAULT NULL;
ALTER TABLE wcf1_uzbot_top ADD FOREIGN KEY (blogEntry) REFERENCES wcf1_user (userID) ON DELETE SET NULL;
