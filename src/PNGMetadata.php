<?php

declare(strict_types=1);

namespace PNGMetadata;

use ArrayObject;
use PNGMetadata\Metadata\MetadataDocument;

/**
 * PNG Metadata (v2 facade).
 *
 * Preserves the v1 public API over the new layered core: parsing
 * (PNGMetadata\Parsing), chunk decoders (PNGMetadata\Chunk) and the typed
 * domain model (PNGMetadata\Metadata).
 *
 * This file is part of photo-metadata.
 *
 * PNGMetadata is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PNGMetadata is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * PNG Metadata class for extraction of XMP, TEXIF, EXIF, BKGD, sRGB and IHDR.
 *
 * Returns the complete information found in the different types
 * of metadata within a PNG format image.
 *
 * @extends ArrayObject<string, mixed>
 */
class PNGMetadata extends ArrayObject
{
	/**
	 * The legacy-shaped metadata (grouped, ksort-ed).
	 *
	 * @var array<string, mixed>
	 */
	private array $metadata = [];

	private ?MetadataDocument $document = null;

	/**
	 * Extract the metadata of a PNG image.
	 *
	 * @param string $path Location of the image on disk.
	 *
	 * @throws \InvalidArgumentException If the file does not exist or is not a PNG.
	 */
	public function __construct(string $path)
	{
		$this->checkPath($path);

		$this->document = PngMetadataReader::create()->read($path);
		$this->metadata = (new LegacyArrayFormatter())->format($this->document);

		parent::__construct($this->metadata);
	}

	/**
	 * Return a new PNGMetadata, or null on any failure.
	 *
	 * @param string|null $path Location of the image on disk.
	 */
	public static function extract(?string $path = null): ?self
	{
		try {
			return new self($path ?? '');
		} catch (\Throwable) {
			return null;
		}
	}

	/**
	 * Return metadata as a plain array (legacy v1 structure).
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->metadata;
	}

	/**
	 * Return a specific metadata value by colon-separated path,
	 * e.g. 'exif:THUMBNAIL:Compression'.
	 *
	 * @return string|mixed[]|null
	 */
	public function get(string $string)
	{
		$return = $this->metadata;
		foreach (\explode(':', $string) as $key) {
			if (\is_array($return) && \array_key_exists($key, $return)) {
				$return = $return[$key];
			} else {
				return null;
			}
		}

		// v1 returned the raw stored value; keep scalars and arrays intact.
		/** @var string|mixed[]|null */
		return \is_array($return) || \is_scalar($return) || $return === null ? $return : null;
	}

	/**
	 * Return the metadata as a two-column string.
	 */
	public function __toString(): string
	{
		$data = $this->printVertical();
		$keys = \array_map('strlen', \array_keys($data));
		$maxLen = ($keys === [] ? 0 : \max($keys)) + 10;
		$strings[] = \str_pad('--Metadata--', $maxLen) . '--Value--' . "\n";

		foreach ($data as $key => $value) {
			$strings[] = \str_pad(\trim((string) $key), $maxLen) . $value . "\n";
		}
		if (PHP_SAPI !== 'cli') {
			\array_unshift($strings, '<pre>');
			$strings[] = '</pre>';
		}

		return \implode(' ', $strings);
	}

	/**
	 * Check if the file is a PNG.
	 *
	 * @param string $path Location of the image on disk.
	 */
	public static function isPNG(string $path): bool
	{
		return self::getType($path) === IMAGETYPE_PNG;
	}

	/**
	 * Get the image type (exif_imagetype).
	 *
	 * @param string $path Location of the image on disk.
	 * @return int|false
	 *
	 * @throws \InvalidArgumentException If the path is empty or the file does not exist.
	 */
	public static function getType(string $path)
	{
		if ($path === '') {
			throw new \InvalidArgumentException('The argument path is empty', 101);
		}
		if (!\file_exists($path)) {
			throw new \InvalidArgumentException('The file path does not exist or it\'s inaccessible', 102);
		}

		return \exif_imagetype($path);
	}

	/**
	 * Get the embedded EXIF thumbnail as a GD image.
	 *
	 * Requires the GD extension only when called.
	 *
	 * @return \GdImage|false
	 */
	public function getThumbnail()
	{
		$bytes = $this->document?->thumbnailBytes();
		if ($bytes === null || $bytes === '') {
			return false;
		}

		return \imagecreatefromstring($bytes);
	}

	/**
	 * Check the file path and that it is a PNG image.
	 *
	 * @param string $path Location of the image on disk.
	 *
	 * @throws \InvalidArgumentException If the file path is not a PNG image.
	 */
	private function checkPath(string $path): void
	{
		if (!self::isPNG($path)) {
			throw new \InvalidArgumentException('The file path isn\'t PNG', 103);
		}
	}

	/**
	 * Flatten nested metadata into colon-joined keys for __toString().
	 *
	 * @param string $lastKey Key prefix from the parent level.
	 * @param mixed[]|null $array Array to flatten (defaults to the metadata).
	 * @return array<string, string>
	 */
	private function printVertical(string $lastKey = '', ?array $array = null): array
	{
		$columns = [];

		if ($lastKey !== '') {
			$lastKey .= ':';
		}

		foreach ($array ?? $this->metadata as $key => $value) {
			if (\is_array($value)) {
				if (isset($value[0])) {
					$columns[] = [$lastKey . $key => \implode(',', \array_map(self::stringify(...), $value))];
				} else {
					$columns[] = $this->printVertical($lastKey . $key, $value);
				}
			} else {
				$columns[] = [$lastKey . $key => self::stringify($value)];
			}
		}

		return $columns === [] ? [] : \array_merge(...$columns);
	}

	/**
	 * Convert a scalar metadata value to string for display.
	 */
	private static function stringify(mixed $value): string
	{
		if (\is_bool($value)) {
			return $value ? '1' : '0';
		}
		if (\is_scalar($value)) {
			return (string) $value;
		}
		if ($value === null) {
			return '';
		}

		return \get_debug_type($value);
	}
}
