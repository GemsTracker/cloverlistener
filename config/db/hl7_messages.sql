
CREATE TABLE IF NOT EXISTS hl7_messages (
        hm_id           INT(11) NOT NULL AUTO_INCREMENT,
        hm_datetime     DATETIME NOT NULL,
        hm_msgid        VARCHAR(20) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hm_processing   VARCHAR(3) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hm_version      VARCHAR(60) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hm_type         VARCHAR(7) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hm_message      TEXT CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,
        hm_created      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (hm_id)
    )
    ENGINE=InnoDB
    DEFAULT CHARSET='utf8' COLLATE=utf8_unicode_ci
    AUTO_INCREMENT=10000000;
