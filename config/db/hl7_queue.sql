
CREATE TABLE IF NOT EXISTS hl7_queue (
        hq_queue_id             INT(11) NOT NULL AUTO_INCREMENT,

        hq_message_id           INT(11) NOT NULL,
        hq_action_class         VARCHAR(255) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NOT NULL,

        hq_execution_attempts   INT(11) NOT NULL DEFAULT 0,
        hq_execution_lock       DATETIME NULL DEFAULT NULL,

        hq_execution_count      INT(11) NOT NULL DEFAULT 0,
        hq_last_execution       DATETIME NULL DEFAULT NULL,

        hq_execution_result     VARCHAR(60) CHARACTER SET 'utf8' COLLATE utf8_unicode_ci NULL,

        hq_created              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        hq_changed              TIMESTAMP NOT NULL,

        PRIMARY KEY (hq_queue_id),
        UNIQUE INDEX (hq_message_id, hq_action_class),
        INDEX (hq_execution_count)
    )
    ENGINE=InnoDB
    DEFAULT CHARSET='utf8' COLLATE=utf8_unicode_ci
    AUTO_INCREMENT=20000000;
