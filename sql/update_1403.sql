-- /*******************************************************
-- *
-- * civicrm_sdd_entity_mandate
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_entity_mandate` (
     `id`                    int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `mandate_id`            int unsigned NOT NULL                 COMMENT 'FK to sdd_mandate',
     `entity_table`          varchar(64)  NOT NULL                 COMMENT 'Physical tablename for entity being linked, eg civicrm_membership',
     `entity_id`             int unsigned NOT NULL                 COMMENT 'FK to entity table specified in entity_table column.',
     `class`                 varchar(16)                           COMMENT 'Link class, freely defined by client',
     `is_active`             tinyint NOT NULL  DEFAULT 1           COMMENT 'Is this link still active?',
     `creation_date`         datetime NOT NULL                     COMMENT 'Link creation date',
     `start_date`            datetime                              COMMENT 'optional start_date of the link',
     `end_date`              datetime                              COMMENT 'optional start_date of the link',

     PRIMARY KEY (`id`),
     INDEX `mandate_id` (mandate_id),
     INDEX `index_entity` (entity_table, entity_id),
     INDEX `class` (class),
     INDEX `is_active` (is_active),
     INDEX `start_date` (start_date),
     INDEX `end_date` (end_date),

     CONSTRAINT FK_civicrm_sdd_entity_mandate_id FOREIGN KEY (`mandate_id`) REFERENCES `civicrm_sdd_mandate`(`id`) ON DELETE CASCADE

)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;

