ALTER TABLE wcf1_uzbot ADD blogEntryData TEXT;
ALTER TABLE wcf1_uzbot ADD blogID INT(10) DEFAULT 0;

ALTER TABLE wcf1_uzbot ADD blogChangeBlogEdit TINYINT(1) DEFAULT 0;
ALTER TABLE wcf1_uzbot ADD blogChangeEntryEdit TINYINT(1) DEFAULT 0;
ALTER TABLE wcf1_uzbot ADD blogChangeEntryDelete TINYINT(1) DEFAULT 0;

ALTER TABLE wcf1_uzbot ADD blogEntryCountAction VARCHAR(15) DEFAULT 'entryTotal';

ALTER TABLE wcf1_uzbot ADD topBloggerCount INT(10) DEFAULT 1;
ALTER TABLE wcf1_uzbot ADD topBloggerInterval TINYINT(1) DEFAULT 1;
ALTER TABLE wcf1_uzbot ADD topBloggerNext INT(10) DEFAULT 0;