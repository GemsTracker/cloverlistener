
CREATE TABLE IF NOT EXISTS hl7_messages (
        hl7_id          INT(11) NOT NULL AUTO_INCREMENT,
        hl7_datetime    DATETIME NOT NULL,
        hl7_msgid       VARCHAR(20) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hl7_processing  VARCHAR(3) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hl7_version     VARCHAR(60) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hl7_type        VARCHAR(7) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hl7_message     TEXT CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hl7_created     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (hl7_id)
    )
    ENGINE=InnoDB
    DEFAULT CHARSET='utf8' COLLATE=utf8_unicode_ci
    AUTO_INCREMENT=10000000;
