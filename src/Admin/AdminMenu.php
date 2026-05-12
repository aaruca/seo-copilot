<?php

namespace SeoCopilot\Admin;

use SeoCopilot\Admin\Pages\BulkWizardPage;
use SeoCopilot\Admin\Pages\DashboardPage;
use SeoCopilot\Admin\Pages\PendingReviewPage;
use SeoCopilot\Admin\Pages\SettingsPage;
use SeoCopilot\Admin\Pages\SmartOptimizerPage;
use SeoCopilot\Admin\Pages\TemplatesPage;
use SeoCopilot\Capabilities\Capabilities;
use SeoCopilot\PostTypes\PostTypeRegistry;

class AdminMenu
{
    public const SLUG_ROOT     = 'seo-copilot';
    public const SLUG_OPTIMIZE = 'seo-copilot-optimize';
    public const SLUG_BULK     = 'seo-copilot-bulk';
    public const SLUG_REVIEW   = 'seo-copilot-pending-review';
    public const SLUG_TEMPLATES= 'seo-copilot-templates';
    public const SLUG_SETTINGS = 'seo-copilot-settings';

    private PostTypeRegistry $registry;

    public function __construct(PostTypeRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void
    {
        $cap_run    = Capabilities::RUN_OPTIMIZER;
        $cap_manage = Capabilities::MANAGE_SETTINGS;
        $cap_tpl    = Capabilities::EDIT_TEMPLATES;

        add_menu_page(
            __('SEO Copilot', 'seo-copilot'),
            __('SEO Copilot', 'seo-copilot'),
            $cap_run,
            self::SLUG_ROOT,
            [DashboardPage::class, 'render'],
            'dashicons-superhero',
            58
        );
        add_submenu_page(self::SLUG_ROOT, __('Dashboard', 'seo-copilot'), __('Dashboard', 'seo-copilot'), $cap_run, self::SLUG_ROOT, [DashboardPage::class, 'render']);
        add_submenu_page(self::SLUG_ROOT, __('Smart Optimizer', 'seo-copilot'), __('Smart Optimizer', 'seo-copilot'), $cap_run, self::SLUG_OPTIMIZE, [SmartOptimizerPage::class, 'render']);
        add_submenu_page(self::SLUG_ROOT, __('Bulk Wizard', 'seo-copilot'), __('Bulk Wizard', 'seo-copilot'), $cap_run, self::SLUG_BULK, [BulkWizardPage::class, 'render']);
        add_submenu_page(self::SLUG_ROOT, __('Pending Review', 'seo-copilot'), __('Pending Review', 'seo-copilot'), $cap_run, self::SLUG_REVIEW, [PendingReviewPage::class, 'render']);
        add_submenu_page(self::SLUG_ROOT, __('Templates', 'seo-copilot'), __('Templates', 'seo-copilot'), $cap_tpl, self::SLUG_TEMPLATES, [TemplatesPage::class, 'render']);
        add_submenu_page(self::SLUG_ROOT, __('Settings', 'seo-copilot'), __('Settings', 'seo-copilot'), $cap_manage, self::SLUG_SETTINGS, [SettingsPage::class, 'render']);
    }
}
