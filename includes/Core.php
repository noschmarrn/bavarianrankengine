<?php
namespace BavarianRankEngine;

class Core {
    private static ?Core $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies(): void {
        require_once BRE_DIR . 'includes/Providers/ProviderInterface.php';
        require_once BRE_DIR . 'includes/Providers/ProviderRegistry.php';
        require_once BRE_DIR . 'includes/Providers/OpenAIProvider.php';
        require_once BRE_DIR . 'includes/Providers/AnthropicProvider.php';
        require_once BRE_DIR . 'includes/Providers/GeminiProvider.php';
        require_once BRE_DIR . 'includes/Providers/GrokProvider.php';
        require_once BRE_DIR . 'includes/Helpers/KeyVault.php';
        require_once BRE_DIR . 'includes/Helpers/TokenEstimator.php';
        require_once BRE_DIR . 'includes/Helpers/BulkQueue.php';
        require_once BRE_DIR . 'includes/Features/MetaGenerator.php';
        require_once BRE_DIR . 'includes/Features/SchemaEnhancer.php';
        require_once BRE_DIR . 'includes/Features/LlmsTxt.php';
        require_once BRE_DIR . 'includes/Admin/SettingsPage.php';
        require_once BRE_DIR . 'includes/Admin/AdminMenu.php';
        require_once BRE_DIR . 'includes/Admin/ProviderPage.php';
        require_once BRE_DIR . 'includes/Admin/MetaPage.php';
        require_once BRE_DIR . 'includes/Admin/BulkPage.php';
        require_once BRE_DIR . 'includes/Admin/LlmsPage.php';
    }

    private function register_hooks(): void {
        $registry = ProviderRegistry::instance();
        $registry->register( new Providers\OpenAIProvider() );
        $registry->register( new Providers\AnthropicProvider() );
        $registry->register( new Providers\GeminiProvider() );
        $registry->register( new Providers\GrokProvider() );

        ( new Features\MetaGenerator() )->register();
        ( new Features\SchemaEnhancer() )->register();
        ( new Features\LlmsTxt() )->register();

        if ( is_admin() ) {
            $menu = new Admin\AdminMenu();
            $menu->register();
            ( new Admin\ProviderPage() )->register();
            ( new Admin\MetaPage() )->register();
            ( new Admin\BulkPage() )->register();
            ( new Admin\LlmsPage() )->register();
        }
    }
}
