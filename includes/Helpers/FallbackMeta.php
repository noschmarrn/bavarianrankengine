<?php
namespace BavarianRankEngine\Helpers;

class FallbackMeta {
    private const MIN = 150;
    private const MAX = 160;

    /**
     * Extract a clean 150–160 char meta description from post content.
     * Ends on a complete sentence or word boundary. No HTML.
     *
     * @param object $post Object with post_content property (WP_Post compatible)
     */
    public static function extract( object $post ): string {
        $text = strip_tags( $post->post_content ?? '' );
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );

        if ( $text === '' ) {
            return '';
        }

        if ( mb_strlen( $text ) <= self::MAX ) {
            return $text;
        }

        // Try to end on a sentence boundary within MAX chars
        $candidate     = mb_substr( $text, 0, self::MAX );
        $last_period   = mb_strrpos( $candidate, '. ' );
        $last_exclaim  = mb_strrpos( $candidate, '! ' );
        $last_question = mb_strrpos( $candidate, '? ' );

        $last_sentence = max(
            $last_period   === false ? -1 : $last_period,
            $last_exclaim  === false ? -1 : $last_exclaim,
            $last_question === false ? -1 : $last_question
        );

        if ( $last_sentence >= 0 && $last_sentence >= self::MIN - 1 ) {
            return mb_substr( $text, 0, $last_sentence + 1 );
        }

        // Fall back to last word boundary within MAX
        $last_space = mb_strrpos( $candidate, ' ' );
        if ( $last_space !== false && $last_space >= self::MIN - 20 ) {
            return mb_substr( $text, 0, $last_space ) . '…';
        }

        // Last resort: hard cut with ellipsis
        return mb_substr( $text, 0, self::MAX - 1 ) . '…';
    }
}
