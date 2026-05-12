<?php

namespace SeoCopilot\Database;

class Schema
{
    /** Internal schema version — independent of plugin version (which is pinned at 1.0.0).
     *  Bump this when you change CREATE TABLE strings so `maybe_upgrade()` runs dbDelta. */
    public const DB_VERSION = '1.1.0';

    public static function install(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix . 'seocp_';

        $sql = [];

        $sql[] = "CREATE TABLE {$p}templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            system_prompt LONGTEXT NULL,
            user_template LONGTEXT NULL,
            json_schema LONGTEXT NULL,
            produces LONGTEXT NULL,
            applies_to_post_types LONGTEXT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            post_type VARCHAR(40) NOT NULL,
            template_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL,
            fields_written LONGTEXT NULL,
            tokens_in INT NOT NULL DEFAULT 0,
            tokens_out INT NOT NULL DEFAULT 0,
            cost DECIMAL(10,6) NOT NULL DEFAULT 0,
            model VARCHAR(60) NULL,
            error_message TEXT NULL,
            batch_id VARCHAR(40) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY batch_id (batch_id),
            KEY created_at (created_at)
        ) {$charset};";

        // Generated proposals waiting for review (Bulk Wizard "Generate for review" mode).
        // approved: 0 = pending, 1 = applied, 2 = rejected.
        $sql[] = "CREATE TABLE {$p}segments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id VARCHAR(40) NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            template_id BIGINT UNSIGNED NULL,
            field_id VARCHAR(80) NOT NULL,
            generated_value LONGTEXT NULL,
            confidence DECIMAL(4,3) NOT NULL DEFAULT 0,
            requires_review TINYINT(1) NOT NULL DEFAULT 1,
            approved TINYINT(1) NOT NULL DEFAULT 0,
            generated_at DATETIME NOT NULL,
            applied_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY batch_id (batch_id),
            KEY field_id (field_id),
            KEY pending (approved, generated_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id VARCHAR(40) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            template_id BIGINT UNSIGNED NOT NULL,
            fields_picked LONGTEXT NULL,
            mode VARCHAR(20) NOT NULL DEFAULT 'apply',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            scheduled_for DATETIME NOT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            error_message TEXT NULL,
            PRIMARY KEY (id),
            KEY batch_id (batch_id),
            KEY status_scheduled (status, scheduled_for)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}options_long (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            option_key VARCHAR(190) NOT NULL,
            option_value LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY option_key (option_key)
        ) {$charset};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option('seocp_db_version', self::DB_VERSION);
    }

    public static function maybe_upgrade(): void
    {
        $current = get_option('seocp_db_version');
        if ($current !== self::DB_VERSION) {
            self::install();
        }
    }

    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'seocp_' . $name;
    }
}
