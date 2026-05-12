<?php

namespace SeoCopilot\Capabilities;

class Capabilities
{
    public const MANAGE_SETTINGS  = 'seocp_manage_settings';
    public const RUN_OPTIMIZER    = 'seocp_run_optimizer';
    public const EDIT_TEMPLATES   = 'seocp_edit_templates';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::MANAGE_SETTINGS, self::RUN_OPTIMIZER, self::EDIT_TEMPLATES];
    }

    public static function ensure(bool $force = false): void
    {
        $stamp = get_option('seocp_caps_seeded');
        if (!$force && $stamp === SEOCP_VERSION) {
            return;
        }

        $map = apply_filters('seocp_capabilities', [
            'administrator' => [self::MANAGE_SETTINGS, self::RUN_OPTIMIZER, self::EDIT_TEMPLATES],
            'shop_manager'  => [self::RUN_OPTIMIZER, self::EDIT_TEMPLATES],
            'editor'        => [self::RUN_OPTIMIZER],
        ]);

        foreach ($map as $role_slug => $caps) {
            $role = get_role($role_slug);
            if (!$role) {
                continue;
            }
            foreach ($caps as $cap) {
                $role->add_cap($cap);
            }
        }

        update_option('seocp_caps_seeded', SEOCP_VERSION);
    }

    public static function user_can_run(): bool
    {
        return current_user_can(self::RUN_OPTIMIZER) || current_user_can('manage_options');
    }

    public static function user_can_manage(): bool
    {
        return current_user_can(self::MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    public static function user_can_edit_templates(): bool
    {
        return current_user_can(self::EDIT_TEMPLATES) || current_user_can('manage_options');
    }
}
