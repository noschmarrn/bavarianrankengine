<?php
namespace BavarianRankEngine\Helpers;

class TokenEstimator {
    /**
     * Pricing per 1k tokens [provider][model][input|output]
     * Update these when provider pricing changes.
     */
    private const PRICING = [
        'openai' => [
            'gpt-4.1'       => [ 'input' => 0.002,  'output' => 0.008  ],
            'gpt-4o'        => [ 'input' => 0.0025, 'output' => 0.01   ],
            'gpt-4o-mini'   => [ 'input' => 0.00015,'output' => 0.0006 ],
            'gpt-3.5-turbo' => [ 'input' => 0.0005, 'output' => 0.0015 ],
        ],
        'anthropic' => [
            'claude-sonnet-4-6'         => [ 'input' => 0.003,  'output' => 0.015   ],
            'claude-opus-4-6'           => [ 'input' => 0.015,  'output' => 0.075   ],
            'claude-haiku-4-5-20251001' => [ 'input' => 0.00025,'output' => 0.00125 ],
        ],
        'gemini' => [
            'gemini-2.0-flash'      => [ 'input' => 0.00010,  'output' => 0.00040 ],
            'gemini-2.0-flash-lite' => [ 'input' => 0.000038, 'output' => 0.00015 ],
            'gemini-1.5-pro'        => [ 'input' => 0.00125,  'output' => 0.005   ],
        ],
        'grok' => [
            'grok-3'      => [ 'input' => 0.003,  'output' => 0.015  ],
            'grok-3-mini' => [ 'input' => 0.0003, 'output' => 0.0005 ],
        ],
    ];

    /** Estimate token count (~4 chars per token) */
    public static function estimate( string $text ): int {
        return (int) ceil( mb_strlen( $text ) / 4 );
    }

    /** Truncate text to approximately $max_tokens */
    public static function truncate( string $text, int $max_tokens ): string {
        $max_chars = $max_tokens * 4;
        if ( mb_strlen( $text ) <= $max_chars ) {
            return $text;
        }
        return mb_substr( $text, 0, $max_chars );
    }

    /**
     * Estimate cost in USD.
     * @param int    $tokens   Number of tokens
     * @param string $provider Provider ID
     * @param string $model    Model ID
     * @param string $type     'input' or 'output'
     */
    public static function estimateCost( int $tokens, string $provider, string $model, string $type = 'input' ): float {
        $price_per_1k = self::PRICING[ $provider ][ $model ][ $type ] ?? 0.002;
        return round( ( $tokens / 1000 ) * $price_per_1k, 6 );
    }

    /** Human-readable cost string e.g. "~0,05 €" */
    public static function formatCost( float $usd ): string {
        $eur = $usd * 0.92;
        if ( $eur < 0.01 ) {
            return '< 0,01 €';
        }
        return '~' . number_format( $eur, 2, ',', '.' ) . ' €';
    }
}
