<?php
/**
 * Serialized Data Handler
 *
 * Safely handles search and replace operations in serialized data
 * by properly updating string length indicators.
 *
 * @package CrispySEO\Tools
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Tools;

/**
 * Handles safe manipulation of serialized data.
 */
class SerializedHandler {

	/**
	 * Check if a string is serialized.
	 *
	 * @param string $data String to check.
	 * @return bool True if serialized.
	 */
	public function isSerialized( string $data ): bool {
		// Quick check for common patterns.
		if ( $data === 'N;' ) {
			return true;
		}

		if ( strlen( $data ) < 4 ) {
			return false;
		}

		// Check for serialized patterns.
		if ( preg_match( '/^[aOsbid]:[0-9]+/', $data ) ) {
			// Try to unserialize to verify.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional for validation.
			$result = @unserialize( $data );
			return $result !== false || $data === 'b:0;';
		}

		return false;
	}

	/**
	 * Check if a string contains serialized data (possibly nested in JSON or other formats).
	 *
	 * @param string $data String to check.
	 * @return bool True if contains serialized data.
	 */
	public function containsSerialized( string $data ): bool {
		// Direct serialized check.
		if ( $this->isSerialized( $data ) ) {
			return true;
		}

		// Check for serialized patterns within the string.
		return (bool) preg_match( '/[aOs]:[0-9]+:["{]/', $data );
	}

	/**
	 * Safely replace strings in serialized data.
	 *
	 * This method handles the complexity of serialized data where string lengths
	 * are encoded. Simply doing str_replace would corrupt the serialization.
	 *
	 * @param string $data    The serialized data.
	 * @param string $search  String to search for.
	 * @param string $replace Replacement string.
	 * @return string|false Modified data or false on failure.
	 */
	public function replaceInSerialized( string $data, string $search, string $replace ): string|false {
		// If not serialized, do simple replacement.
		if ( ! $this->isSerialized( $data ) ) {
			return str_replace( $search, $replace, $data );
		}

		// Unserialize the data.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional for handling corrupt data.
		$unserialized = @unserialize( $data );

		if ( $unserialized === false && $data !== 'b:0;' ) {
			// Data is corrupt or not actually serialized.
			return false;
		}

		// Recursively replace in the unserialized data.
		$modified = $this->recursiveReplace( $unserialized, $search, $replace );

		// Re-serialize.
		$result = serialize( $modified );

		// Verify the result is valid.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional for validation.
		$verify = @unserialize( $result );
		if ( $verify === false && $result !== 'b:0;' ) {
			return false;
		}

		return $result;
	}

	/**
	 * Recursively replace strings in an array or object.
	 *
	 * @param mixed  $data    Data to process.
	 * @param string $search  String to search for.
	 * @param string $replace Replacement string.
	 * @return mixed Modified data.
	 */
	private function recursiveReplace( mixed $data, string $search, string $replace ): mixed {
		if ( is_string( $data ) ) {
			// Check if this string itself contains serialized data.
			if ( $this->isSerialized( $data ) ) {
				$result = $this->replaceInSerialized( $data, $search, $replace );
				return $result !== false ? $result : str_replace( $search, $replace, $data );
			}
			return str_replace( $search, $replace, $data );
		}

		if ( is_array( $data ) ) {
			$result = [];
			foreach ( $data as $key => $value ) {
				// Also replace in keys if they're strings.
				$newKey            = is_string( $key ) ? str_replace( $search, $replace, $key ) : $key;
				$result[ $newKey ] = $this->recursiveReplace( $value, $search, $replace );
			}
			return $result;
		}

		if ( is_object( $data ) ) {
			// Handle stdClass and other objects.
			$className = get_class( $data );

			if ( $className === 'stdClass' ) {
				$result = new \stdClass();
				foreach ( get_object_vars( $data ) as $key => $value ) {
					$newKey           = str_replace( $search, $replace, $key );
					$result->$newKey = $this->recursiveReplace( $value, $search, $replace );
				}
				return $result;
			}

			// For other objects, try to handle their properties.
			try {
				$reflection = new \ReflectionClass( $data );
				$clone      = clone $data;

				foreach ( $reflection->getProperties() as $property ) {
					$property->setAccessible( true );
					if ( $property->isInitialized( $clone ) ) {
						$value = $property->getValue( $clone );
						$property->setValue( $clone, $this->recursiveReplace( $value, $search, $replace ) );
					}
				}

				return $clone;
			} catch ( \ReflectionException $e ) {
				// Cannot handle this object type, return as-is.
				return $data;
			}
		}

		// For other types (int, float, bool, null), return as-is.
		return $data;
	}

	/**
	 * Fix broken serialized data by recalculating string lengths.
	 *
	 * This can help recover data that was corrupted by naive string replacement.
	 *
	 * @param string $data Potentially broken serialized data.
	 * @return string|false Fixed data or false if unfixable.
	 */
	public function fixSerialized( string $data ): string|false {
		// Try to fix string length mismatches.
		$pattern = '/s:(\d+):"(.*?)";/s';

		$fixed = preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$content    = $matches[2];
				$realLength = strlen( $content );
				return 's:' . $realLength . ':"' . $content . '";';
			},
			$data
		);

		if ( $fixed === null ) {
			return false;
		}

		// Verify the fix worked.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional for validation.
		$test = @unserialize( $fixed );
		if ( $test === false && $fixed !== 'b:0;' ) {
			return false;
		}

		return $fixed;
	}

	/**
	 * Get information about serialized data structure.
	 *
	 * @param string $data Serialized data.
	 * @return array{type: string, valid: bool, depth: int, string_count: int} Structure info.
	 */
	public function analyzeStructure( string $data ): array {
		$info = [
			'type'         => 'unknown',
			'valid'        => false,
			'depth'        => 0,
			'string_count' => 0,
		];

		if ( ! $this->isSerialized( $data ) ) {
			$info['type'] = 'not_serialized';
			return $info;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional for validation.
		$unserialized = @unserialize( $data );

		if ( $unserialized === false && $data !== 'b:0;' ) {
			$info['type'] = 'corrupt';
			return $info;
		}

		$info['valid'] = true;
		$info['type']  = gettype( $unserialized );

		// Calculate depth and string count.
		$this->calculateDepth( $unserialized, 0, $info['depth'], $info['string_count'] );

		return $info;
	}

	/**
	 * Recursively calculate depth and count strings.
	 *
	 * @param mixed $data         Data to analyze.
	 * @param int   $currentDepth Current depth level.
	 * @param int   $maxDepth     Maximum depth found (by reference).
	 * @param int   $stringCount  String count (by reference).
	 */
	private function calculateDepth( mixed $data, int $currentDepth, int &$maxDepth, int &$stringCount ): void {
		$maxDepth = max( $maxDepth, $currentDepth );

		if ( is_string( $data ) ) {
			++$stringCount;
			return;
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$this->calculateDepth( $value, $currentDepth + 1, $maxDepth, $stringCount );
			}
			return;
		}

		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $value ) {
				$this->calculateDepth( $value, $currentDepth + 1, $maxDepth, $stringCount );
			}
		}
	}

	/**
	 * Extract all strings from serialized data.
	 *
	 * Useful for previewing what will be affected by a replacement.
	 *
	 * @param string $data   Serialized data.
	 * @param string $search Optional search term to filter strings.
	 * @return array<string> List of strings found.
	 */
	public function extractStrings( string $data, string $search = '' ): array {
		$strings = [];

		if ( ! $this->isSerialized( $data ) ) {
			if ( empty( $search ) || str_contains( $data, $search ) ) {
				$strings[] = $data;
			}
			return $strings;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional for validation.
		$unserialized = @unserialize( $data );

		if ( $unserialized === false && $data !== 'b:0;' ) {
			return $strings;
		}

		$this->collectStrings( $unserialized, $search, $strings );

		return $strings;
	}

	/**
	 * Recursively collect strings from data.
	 *
	 * @param mixed         $data    Data to process.
	 * @param string        $search  Search term filter.
	 * @param array<string> $strings Collected strings (by reference).
	 */
	private function collectStrings( mixed $data, string $search, array &$strings ): void {
		if ( is_string( $data ) ) {
			if ( empty( $search ) || str_contains( $data, $search ) ) {
				$strings[] = $data;
			}
			return;
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$this->collectStrings( $value, $search, $strings );
			}
			return;
		}

		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $value ) {
				$this->collectStrings( $value, $search, $strings );
			}
		}
	}
}
