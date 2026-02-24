<?php
namespace BavarianRankEngine\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\Admin\SettingsPage;
use BavarianRankEngine\Admin\SchemaMetaBox;

class SchemaEnhancer {
	public function register(): void {
		$settings = SettingsPage::getSettings();
		$enabled  = $settings['schema_enabled'] ?? array();

		if ( empty( $enabled ) ) {
			return;
		}

		if ( in_array( 'ai_meta_tags', $enabled, true ) ) {
			add_action( 'wp_head', array( $this, 'outputAiMetaTags' ), 1 );
		}

		$json_ld_types = array_diff( $enabled, array( 'ai_meta_tags' ) );
		if ( ! empty( $json_ld_types ) ) {
			add_action( 'wp_head', array( $this, 'outputJsonLd' ), 5 );
		}

		add_action( 'wp_head', array( $this, 'outputMetaDescription' ), 2 );
	}

	public function outputAiMetaTags(): void {
		echo '<meta name="robots" content="max-snippet:-1, max-image-preview:large, max-video-preview:-1">' . "\n";
		echo '<meta name="googlebot" content="max-snippet:-1, max-image-preview:large">' . "\n";
	}

	public function outputMetaDescription(): void {
		if ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
			return;
		}
		if ( ! is_singular() ) {
			return;
		}

		$desc = get_post_meta( get_the_ID(), '_bre_meta_description', true );
		if ( empty( $desc ) ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}

	public function outputJsonLd(): void {
		$settings = SettingsPage::getSettings();
		$enabled  = $settings['schema_enabled'] ?? array();
		$schemas  = array();

		if ( in_array( 'organization', $enabled, true ) ) {
			$schemas[] = $this->buildOrganizationSchema( $settings );
		}

		if ( is_singular() ) {
			if ( in_array( 'article_about', $enabled, true ) ) {
				$schemas[] = $this->buildArticleSchema();
			}
			if ( in_array( 'author', $enabled, true ) ) {
				$schemas[] = $this->buildAuthorSchema();
			}
			if ( in_array( 'speakable', $enabled, true ) ) {
				$schemas[] = $this->buildSpeakableSchema();
			}
		}

		if ( in_array( 'breadcrumb', $enabled, true )
			&& ! defined( 'RANK_MATH_VERSION' )
			&& ! defined( 'WPSEO_VERSION' ) ) {
			$breadcrumb = $this->buildBreadcrumbSchema();
			if ( $breadcrumb ) {
				$schemas[] = $breadcrumb;
			}
		}

		foreach ( $schemas as $schema ) {
			echo '<script type="application/ld+json">'
				. wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
				. '</script>' . "\n";
		}
	}

	private function buildOrganizationSchema( array $settings ): array {
		$same_as = array_values( array_filter( $settings['schema_same_as']['organization'] ?? array() ) );
		$schema  = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		);
		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = $same_as;
		}
		$logo = get_site_icon_url( 192 );
		if ( $logo ) {
			$schema['logo'] = $logo;
		}
		return $schema;
	}

	private function buildArticleSchema(): array {
		return array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Article',
			'headline'      => get_the_title(),
			'url'           => get_permalink(),
			'datePublished' => get_the_date( 'c' ),
			'dateModified'  => get_the_modified_date( 'c' ),
			'description'   => get_post_meta( get_the_ID(), '_bre_meta_description', true ) ?: get_the_excerpt(),
			'publisher'     => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
		);
	}

	private function buildAuthorSchema(): array {
		$author_id = (int) get_the_author_meta( 'ID' );
		$schema    = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'name'     => get_the_author(),
			'url'      => get_author_posts_url( $author_id ),
		);
		$twitter   = get_the_author_meta( 'twitter', $author_id );
		if ( $twitter ) {
			$schema['sameAs'] = array( 'https://twitter.com/' . ltrim( $twitter, '@' ) );
		}
		return $schema;
	}

	private function buildSpeakableSchema(): array {
		return array(
			'@context'  => 'https://schema.org',
			'@type'     => 'WebPage',
			'url'       => get_permalink(),
			'speakable' => array(
				'@type'       => 'SpeakableSpecification',
				'cssSelector' => array( 'h1', '.entry-content p:first-of-type', '.post-content p:first-of-type' ),
			),
		);
	}

	private function buildBreadcrumbSchema(): ?array {
		if ( ! is_singular() && ! is_category() ) {
			return null;
		}

		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => get_bloginfo( 'name' ),
				'item'     => home_url( '/' ),
			),
		);

		if ( is_singular() ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => get_the_title(),
				'item'     => get_permalink(),
			);
		}

		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/**
	 * Pure helper — converts GEO FAQ pairs to FAQPage schema.
	 * Returns null when the list is empty (skip empty schemas).
	 *
	 * @param array $faq  Array of ['q' => string, 'a' => string] pairs.
	 */
	public static function faqPairsToSchema( array $faq ): ?array {
		$entities = [];
		foreach ( $faq as $item ) {
			if ( empty( $item['q'] ) || empty( $item['a'] ) ) {
				continue;
			}
			$entities[] = [
				'@type'          => 'Question',
				'name'           => $item['q'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $item['a'],
				],
			];
		}
		if ( empty( $entities ) ) {
			return null;
		}
		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		];
	}

	/**
	 * WP-dependent wrapper: reads from GeoBlock post meta.
	 */
	private function buildFaqSchema(): ?array {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return null;
		}
		$meta = \BavarianRankEngine\Features\GeoBlock::getMeta( $post_id );
		return self::faqPairsToSchema( $meta['faq'] ?? [] );
	}


    /**
     * Converts integer minutes to ISO 8601 duration string (e.g. 90 -> "PT90M").
     */
    public static function minutesToIsoDuration( int $minutes ): string {
        return 'PT' . $minutes . 'M';
    }

    /**
     * BlogPosting (or Article for non-post types) with embedded author + image.
     */
    private function buildBlogPosting(): array {
        $type   = get_post_type() === 'post' ? 'BlogPosting' : 'Article';
        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => $type,
            'headline'      => get_the_title(),
            'url'           => get_permalink(),
            'datePublished' => get_the_date( 'c' ),
            'dateModified'  => get_the_modified_date( 'c' ),
            'description'   => get_post_meta( get_the_ID(), '_bre_meta_description', true )
                               ?: get_the_excerpt(),
            'publisher'     => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url( '/' ),
            ),
            'author'        => array(
                '@type' => 'Person',
                'name'  => get_the_author(),
                'url'   => get_author_posts_url( (int) get_the_author_meta( 'ID' ) ),
            ),
        );
        $img = $this->buildImageObject();
        if ( $img ) {
            $schema['image'] = $img;
        }
        return $schema;
    }

    /**
     * ImageObject from featured image. Returns null when no thumbnail is set.
     */
    private function buildImageObject(): ?array {
        if ( ! has_post_thumbnail() ) {
            return null;
        }
        $src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
        if ( ! $src ) {
            return null;
        }
        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'ImageObject',
            'contentUrl' => $src[0],
        );
        if ( ! empty( $src[1] ) ) {
            $schema['width'] = (int) $src[1];
        }
        if ( ! empty( $src[2] ) ) {
            $schema['height'] = (int) $src[2];
        }
        return $schema;
    }


    /**
     * Extracts first YouTube or Vimeo video from HTML content.
     * Returns ['platform' => 'youtube'|'vimeo', 'videoId' => string] or null.
     */
    public static function extractVideoFromContent( string $content ): ?array {
        // YouTube embed or youtu.be
        if ( preg_match(
            '#(?:youtube\.com/embed/|youtu\.be/)([a-zA-Z0-9_\-]{11})#',
            $content,
            $m
        ) ) {
            return array( 'platform' => 'youtube', 'videoId' => $m[1] );
        }
        // Vimeo
        if ( preg_match( '#player\.vimeo\.com/video/(\d+)#', $content, $m ) ) {
            return array( 'platform' => 'vimeo', 'videoId' => $m[1] );
        }
        return null;
    }

    /**
     * WP-dependent wrapper: builds VideoObject from first video found in post content.
     */
    private function buildVideoObject(): ?array {
        global $post;
        $content = isset( $post->post_content ) ? $post->post_content : '';
        $video   = self::extractVideoFromContent( $content );
        if ( ! $video ) {
            return null;
        }
        if ( $video['platform'] === 'youtube' ) {
            $embed_url     = 'https://www.youtube.com/embed/' . $video['videoId'];
            $thumbnail_url = 'https://i.ytimg.com/vi/' . $video['videoId'] . '/hqdefault.jpg';
        } else {
            $embed_url     = 'https://player.vimeo.com/video/' . $video['videoId'];
            $thumbnail_url = '';
        }
        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'VideoObject',
            'name'       => get_the_title(),
            'embedUrl'   => $embed_url,
            'uploadDate' => get_the_date( 'c' ),
        );
        if ( $thumbnail_url ) {
            $schema['thumbnailUrl'] = $thumbnail_url;
        }
        return $schema;
    }

	/**
	 * Pure builder for HowTo schema.
	 *
	 * @param string   $name  The how-to title.
	 * @param string[] $steps Each step as a string.
	 */
	public static function buildHowToFromData( string $name, array $steps ): array {
		$how_to_steps = array();
		foreach ( array_filter( array_map( 'trim', $steps ) ) as $step ) {
			$how_to_steps[] = array(
				'@type' => 'HowToStep',
				'name'  => $step,
			);
		}
		return array(
			'@context' => 'https://schema.org',
			'@type'    => 'HowTo',
			'name'     => $name,
			'step'     => $how_to_steps,
		);
	}

	/**
	 * WP-dependent: builds HowTo from post meta.
	 */
	private function buildHowToSchema(): ?array {
		$post_id  = get_the_ID();
		$raw_data = get_post_meta( $post_id, SchemaMetaBox::META_DATA, true ) ?: '{}';
		$data     = json_decode( $raw_data, true );
		$howto    = isset( $data['howto'] ) && is_array( $data['howto'] ) ? $data['howto'] : array();
		$name     = $howto['name'] ?? '';
		$steps    = $howto['steps'] ?? array();
		if ( empty( $name ) || empty( $steps ) ) {
			return null;
		}
		return self::buildHowToFromData( $name, $steps );
	}
}
