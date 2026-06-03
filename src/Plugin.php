<?php

namespace SeoCopilot;

use SeoCopilot\Admin\AdminMenu;
use SeoCopilot\Admin\Assets;
use SeoCopilot\Admin\MetaBox;
use SeoCopilot\Capabilities\Capabilities;
use SeoCopilot\Database\Schema;
use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\Fields\Providers\AIOSEOFieldsProvider;
use SeoCopilot\Fields\Providers\CoreFieldsProvider;
use SeoCopilot\Fields\Providers\RankMathFieldsProvider;
use SeoCopilot\Fields\Providers\SeoPressFieldsProvider;
use SeoCopilot\Fields\Providers\WooCommerceFieldsProvider;
use SeoCopilot\Fields\Providers\YoastFieldsProvider;
use SeoCopilot\PostTypes\PostTypeProbe;
use SeoCopilot\PostTypes\PostTypeRegistry;
use SeoCopilot\Providers\OpenAIBatchClient;
use SeoCopilot\Providers\OpenAIProvider;
use SeoCopilot\Providers\PromptAssembler;
use SeoCopilot\Rest\FieldsController;
use SeoCopilot\Rest\OptimizeController;
use SeoCopilot\Rest\PostsController;
use SeoCopilot\Rest\PostTypesController;
use SeoCopilot\Rest\PreviewController;
use SeoCopilot\Rest\RunsController;
use SeoCopilot\Rest\SegmentsController;
use SeoCopilot\Rest\TemplatesController;
use SeoCopilot\Runs\BatchDispatcher;
use SeoCopilot\Runs\BulkRunner;
use SeoCopilot\Runs\OpenAIBatchRepository;
use SeoCopilot\Runs\RunRepository;
use SeoCopilot\Runs\Runner;
use SeoCopilot\Runs\SegmentRepository;
use SeoCopilot\Support\BricksContentExtractor;
use SeoCopilot\Support\Logger;
use SeoCopilot\Support\PostSnapshotFactory;
use SeoCopilot\Support\RateLimiter;
use SeoCopilot\Templates\DefaultTemplates;
use SeoCopilot\Templates\TemplateRepository;

class Plugin
{
    private static ?self $instance = null;
    private Container $container;
    private bool $booted = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->container = new Container();
        $this->registerServices();
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain('seo-copilot', false, dirname(SEOCP_BASENAME) . '/languages');

        // Capabilities + schema upgrade check on every load.
        Capabilities::ensure();
        Schema::maybe_upgrade();

        // Re-seed default templates whenever the definitions actually change.
        // Version is pinned at 1.0.0 so we can't key off it — hash the seeder's
        // definitions instead. This still catches the upgrade path even when
        // the uploader's "replace existing" skips activation hooks.
        $defaults = new DefaultTemplates($this->container->get(TemplateRepository::class));
        $hash = $defaults->definitions_hash();
        if (get_option('seocp_templates_hash') !== $hash) {
            $defaults->seed();
            update_option('seocp_templates_hash', $hash);
        }

        // Field providers self-register based on environment.
        $registry = $this->container->get(FieldRegistry::class);
        (new CoreFieldsProvider())->register($registry);
        if (class_exists('WooCommerce')) {
            (new WooCommerceFieldsProvider())->register($registry);
        }
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            (new RankMathFieldsProvider())->register($registry);
        }
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            (new YoastFieldsProvider())->register($registry);
        }
        if (defined('AIOSEO_VERSION') || function_exists('aioseo')) {
            (new AIOSEOFieldsProvider())->register($registry);
        }
        if (defined('SEOPRESS_VERSION') || function_exists('seopress_get_locale') || function_exists('seopress_get_service')) {
            (new SeoPressFieldsProvider())->register($registry);
        }

        // Admin surfaces.
        if (is_admin()) {
            $this->container->get(AdminMenu::class)->register();
            $this->container->get(Assets::class)->register();
            $this->container->get(MetaBox::class)->register();
        }

        // REST.
        add_action('rest_api_init', function () {
            $this->container->get(PostTypesController::class)->register_routes();
            $this->container->get(FieldsController::class)->register_routes();
            $this->container->get(PostsController::class)->register_routes();
            $this->container->get(TemplatesController::class)->register_routes();
            $this->container->get(OptimizeController::class)->register_routes();
            $this->container->get(RunsController::class)->register_routes();
            $this->container->get(PreviewController::class)->register_routes();
            $this->container->get(SegmentsController::class)->register_routes();
        });

        // WP-Cron worker. Order matters: the `cron_schedules` filter MUST be
        // registered BEFORE `wp_schedule_event` runs, because wp_schedule_event
        // calls wp_get_schedules() which fires the filter. If `seocp_minute`
        // isn't in the schedule map at that moment, scheduling silently fails
        // and the queue never advances. (This was the 1.0.3 bulk-stuck-at-zero bug.)
        add_filter('cron_schedules', static function ($s) {
            $s['seocp_minute'] = ['interval' => 60, 'display' => 'SEO Copilot — every minute'];
            return $s;
        });
        add_action('seocp_run_bulk_batch', [$this->container->get(BulkRunner::class), 'run_due_batches']);
        if (!wp_next_scheduled('seocp_run_bulk_batch')) {
            wp_schedule_event(time() + 60, 'seocp_minute', 'seocp_run_bulk_batch');
        }
    }

    private function registerServices(): void
    {
        $c = $this->container;
        $c->set(Logger::class, fn() => new Logger());
        $c->set(RateLimiter::class, fn() => new RateLimiter());
        $c->set(PostTypeRegistry::class, fn() => new PostTypeRegistry(new PostTypeProbe()));
        $c->set(FieldRegistry::class, fn() => new FieldRegistry());
        $c->set(BricksContentExtractor::class, fn() => new BricksContentExtractor());
        $c->set(PostSnapshotFactory::class, fn($c) => new PostSnapshotFactory($c->get(BricksContentExtractor::class)));
        $c->set(TemplateRepository::class, fn() => new TemplateRepository());
        $c->set(OpenAIProvider::class, fn($c) => new OpenAIProvider($c->get(Logger::class), $c->get(RateLimiter::class)));
        $c->set(PromptAssembler::class, fn($c) => new PromptAssembler($c->get(PostSnapshotFactory::class)));
        $c->set(RunRepository::class, fn() => new RunRepository());
        $c->set(Runner::class, fn($c) => new Runner(
            $c->get(TemplateRepository::class),
            $c->get(FieldRegistry::class),
            $c->get(PromptAssembler::class),
            $c->get(OpenAIProvider::class),
            $c->get(RunRepository::class),
            $c->get(Logger::class)
        ));
        $c->set(SegmentRepository::class, fn() => new SegmentRepository());
        $c->set(OpenAIBatchClient::class, fn($c) => new OpenAIBatchClient($c->get(Logger::class)));
        $c->set(OpenAIBatchRepository::class, fn() => new OpenAIBatchRepository());
        $c->set(BatchDispatcher::class, fn($c) => new BatchDispatcher(
            $c->get(OpenAIBatchClient::class),
            $c->get(PromptAssembler::class),
            $c->get(TemplateRepository::class),
            $c->get(Runner::class),
            $c->get(SegmentRepository::class),
            $c->get(OpenAIBatchRepository::class),
            $c->get(Logger::class)
        ));
        $c->set(BulkRunner::class, fn($c) => new BulkRunner(
            $c->get(Runner::class),
            $c->get(Logger::class),
            $c->get(SegmentRepository::class),
            $c->get(BatchDispatcher::class)
        ));

        // Admin.
        $c->set(AdminMenu::class, fn($c) => new AdminMenu($c->get(PostTypeRegistry::class)));
        $c->set(Assets::class, fn() => new Assets());
        $c->set(MetaBox::class, fn($c) => new MetaBox($c->get(PostTypeRegistry::class), $c->get(FieldRegistry::class), $c->get(TemplateRepository::class)));

        // REST.
        $c->set(PostTypesController::class, fn($c) => new PostTypesController($c->get(PostTypeRegistry::class)));
        $c->set(FieldsController::class, fn($c) => new FieldsController($c->get(FieldRegistry::class), $c->get(PostTypeRegistry::class)));
        $c->set(PostsController::class, fn($c) => new PostsController($c->get(PostTypeRegistry::class), $c->get(FieldRegistry::class)));
        $c->set(TemplatesController::class, fn($c) => new TemplatesController($c->get(TemplateRepository::class)));
        $c->set(OptimizeController::class, fn($c) => new OptimizeController($c->get(Runner::class), $c->get(FieldRegistry::class)));
        $c->set(RunsController::class, fn($c) => new RunsController($c->get(RunRepository::class), $c->get(BulkRunner::class)));
        $c->set(PreviewController::class, fn($c) => new PreviewController($c->get(PromptAssembler::class), $c->get(TemplateRepository::class)));
        $c->set(SegmentsController::class, fn($c) => new SegmentsController($c->get(SegmentRepository::class), $c->get(Runner::class), $c->get(FieldRegistry::class)));
    }
}
