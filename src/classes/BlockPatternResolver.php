<?php
/**
 * Block Pattern Resolver
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit;

use WP_Block_Patterns_Registry;

/**
 * Resolve blockPattern values to block markup.
 */
class BlockPatternResolver {

	/**
	 * Resolve a block pattern slug to block markup.
	 *
	 * @param string $pattern_slug Pattern slug from block attributes.
	 * @param string $block_path Block path for local pattern fallback.
	 *
	 * @return string
	 */
	public static function resolve( string $pattern_slug, string $block_path ): string {
		$pattern_slug = trim( $pattern_slug );
		if ( '' === $pattern_slug ) {
			return '';
		}

		$registered = self::resolve_registered( $pattern_slug );
		if ( '' !== $registered ) {
			return $registered;
		}

		return self::resolve_local_file( self::basename( $pattern_slug ), $block_path );
	}

	/**
	 * Resolve from registered block patterns.
	 *
	 * @param string $pattern_slug Pattern slug.
	 *
	 * @return string
	 */
	private static function resolve_registered( string $pattern_slug ): string {
		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( self::registered_slug_candidates( $pattern_slug ) as $candidate ) {
			$pattern = $registry->get_registered( $candidate );
			if ( ! empty( $pattern['content'] ) ) {
				return (string) $pattern['content'];
			}
		}

		if ( str_contains( $pattern_slug, '/' ) ) {
			return '';
		}

		if ( ! method_exists( $registry, 'get_all_registered' ) ) {
			return '';
		}

		$needle   = '/' . self::basename( $pattern_slug );
		$patterns = $registry->get_all_registered();

		foreach ( $patterns as $slug => $pattern ) {
			if ( ! str_ends_with( (string) $slug, $needle ) ) {
				continue;
			}

			if ( ! empty( $pattern['content'] ) ) {
				return (string) $pattern['content'];
			}
		}

		return '';
	}

	/**
	 * Resolve from block-local pattern files.
	 *
	 * @param string $basename Pattern basename.
	 * @param string $block_path Block path.
	 *
	 * @return string
	 */
	private static function resolve_local_file( string $basename, string $block_path ): string {
		if ( '' === $basename || '' === trim( $block_path ) ) {
			return '';
		}

		$pattern_dir = trailingslashit( $block_path ) . 'patterns/';
		$candidates  = [
			$pattern_dir . $basename . '.php',
			$pattern_dir . $basename . '.html',
			$pattern_dir . $basename . '.twig',
		];

		foreach ( $candidates as $candidate ) {
			if ( ! file_exists( $candidate ) ) {
				continue;
			}

			$extension = pathinfo( $candidate, PATHINFO_EXTENSION );

			if ( 'php' === $extension ) {
				ob_start();
				include $candidate;
				$content = ob_get_clean();
				return is_string( $content ) ? $content : '';
			}

			if ( 'html' === $extension ) {
				$content = file_get_contents( $candidate );
				return false !== $content ? $content : '';
			}

			if ( 'twig' === $extension && class_exists( '\Timber\Timber' ) ) {
				$template = file_get_contents( $candidate );
				if ( false === $template ) {
					return '';
				}

				return \Timber\Timber::compile_string( $template, [] );
			}
		}

		return '';
	}

	/**
	 * Build direct registered slug candidates.
	 *
	 * @param string $pattern_slug Pattern slug.
	 *
	 * @return array
	 */
	private static function registered_slug_candidates( string $pattern_slug ): array {
		$candidates = [ $pattern_slug ];

		if ( str_contains( $pattern_slug, '/' ) ) {
			$candidates[] = self::basename( $pattern_slug );
		}

		return array_values( array_unique( array_filter( $candidates ) ) );
	}

	/**
	 * Get slug basename.
	 *
	 * @param string $pattern_slug Pattern slug.
	 *
	 * @return string
	 */
	private static function basename( string $pattern_slug ): string {
		$parts = explode( '/', $pattern_slug );
		return sanitize_title( (string) end( $parts ) );
	}
}
