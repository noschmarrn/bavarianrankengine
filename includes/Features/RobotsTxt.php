<?php
namespace BavarianRankEngine\Features;

class RobotsTxt {
    private const OPTION_KEY = 'bre_robots_settings';

    public const KNOWN_BOTS = [
        'GPTBot'            => 'OpenAI GPTBot',
        'ClaudeBot'         => 'Anthropic ClaudeBot',
        'Google-Extended'   => 'Google Extended (Bard/Gemini Training)',
        'PerplexityBot'     => 'Perplexity AI',
        'CCBot'             => 'Common Crawl (CCBot)',
        'Applebot-Extended' => 'Apple AI (Applebot-Extended)',
        'Bytespider'        => 'ByteDance Bytespider',
        'DataForSeoBot'     => 'DataForSEO Bot',
        'ImagesiftBot'      => 'Imagesift Bot',
        'omgili'            => 'Omgili Bot',
        'Diffbot'           => 'Diffbot',
        'FacebookBot'       => 'Meta FacebookBot',
        'Amazonbot'         => 'Amazon Amazonbot',
    ];

    public function register(): void {
        add_filter( 'robots_txt', [ $this, 'append_rules' ], 20, 2 );
    }

    public function append_rules( string $output, bool $public ): string {
        $settings = self::getSettings();
        $blocked  = $settings['blocked_bots'] ?? [];

        foreach ( $blocked as $bot ) {
            if ( isset( self::KNOWN_BOTS[ $bot ] ) ) {
                $output .= "\nUser-agent: {$bot}\nDisallow: /\n";
            }
        }

        return $output;
    }

    public static function getSettings(): array {
        $saved = get_option( self::OPTION_KEY, [] );
        return array_merge(
            [ 'blocked_bots' => [] ],
            is_array( $saved ) ? $saved : []
        );
    }
}
