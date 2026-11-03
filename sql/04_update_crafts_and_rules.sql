-- =====================================================================
-- Update the crafts and join guidelines on an EXISTING database.
-- Back up the database first. This replaces any rules edited by the site admin.
-- Select the right database before importing; do not run 01_schema.sql on existing data.
-- The old combined craft becomes Knitting and Crochet; existing links are kept for both.
-- Run only when the old names do not already coexist with their new names.
-- A fresh database gets these crafts and rules from 01_schema.sql instead.
-- =====================================================================
SET NAMES utf8mb4;

START TRANSACTION;

-- Keep both crafts for anyone who selected the old combined category.
INSERT IGNORE INTO `Category` (`CategoryName`) VALUES ('Crochet');
INSERT IGNORE INTO `UserCategory` (`UserID`, `CategoryID`)
SELECT uc.`UserID`, crochet.`CategoryID`
  FROM `UserCategory` uc
  JOIN `Category` old ON old.`CategoryID` = uc.`CategoryID`
  JOIN `Category` crochet ON crochet.`CategoryName` = 'Crochet'
 WHERE old.`CategoryName` = 'Knitting and crochet';

-- Keep the same meaning for posts linked to the old combined category.
INSERT IGNORE INTO `PostCategory` (`PostID`, `CategoryID`)
SELECT pc.`PostID`, crochet.`CategoryID`
  FROM `PostCategory` pc
  JOIN `Category` old ON old.`CategoryID` = pc.`CategoryID`
  JOIN `Category` crochet ON crochet.`CategoryName` = 'Crochet'
 WHERE old.`CategoryName` = 'Knitting and crochet';

-- Rename old crafts without changing their IDs, so existing links stay intact.
UPDATE `Category` SET `CategoryName` = 'Knitting' WHERE `CategoryName` = 'Knitting and crochet';
UPDATE `Category` SET `CategoryName` = 'Glasswork' WHERE `CategoryName` = 'Glass';
UPDATE `Category` SET `CategoryName` = 'Paper craft' WHERE `CategoryName` = 'Paper crafts';
UPDATE `Category` SET `CategoryName` = 'Textile art' WHERE `CategoryName` = 'Textiles';

-- Add the design's crafts; existing names are skipped by the unique name index.
INSERT IGNORE INTO `Category` (`CategoryName`) VALUES
    ('Crochet'),
    ('Knitting'),
    ('Embroidery'),
    ('Cross-stitch'),
    ('Macramé'),
    ('Weaving'),
    ('Sewing'),
    ('Quilting'),
    ('Felt craft'),
    ('Textile art'),
    ('Woodwork'),
    ('Leatherwork'),
    ('Metalwork'),
    ('Glasswork'),
    ('Ceramics'),
    ('Clay craft'),
    ('Sculpture'),
    ('Beadwork'),
    ('Jewellery'),
    ('Painting'),
    ('Drawing'),
    ('Illustration'),
    ('Digital art'),
    ('Printmaking'),
    ('Calligraphy'),
    ('Paper craft'),
    ('Origami'),
    ('Bookbinding'),
    ('Candle making');

-- Replace the rules with the design's four blocks, shown as cards in join step 3.
UPDATE `SiteSetting` SET `RulesAndRegulations` = 'Post only work made by human hands
No generative AI images, no AI-assisted fills, no AI upscaling. Photograph or scan the thing you made.

Credit patterns, tutorials and teachers
If you followed someone''s pattern or technique, name them. Sharing lineage is part of the craft.

Critique the work, never the person
Beginners post here. Be specific, be useful, and assume the maker is in the room, because they are.

Report AI content instead of arguing with it
Flag it and move on. Moderators review every report, and repeat posting means removal.'
 WHERE `SiteSettingID` = 1;

COMMIT;
