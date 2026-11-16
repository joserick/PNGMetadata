<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * Decoded bKGD chunk. Exactly one variant is populated, chosen by the IHDR
 * color type: a palette index, a gray sample, or an RGB triple.
 */
final readonly class BackgroundColor
{
	private function __construct(
		public ?int $paletteIndex,
		public ?int $gray,
		public ?int $red,
		public ?int $green,
		public ?int $blue,
	) {
	}

	/**
	 * Background for indexed-color images (single palette index).
	 */
	public static function paletteIndex(int $index): self
	{
		return new self($index, null, null, null, null);
	}

	/**
	 * Background for grayscale images, with or without alpha.
	 */
	public static function grayscale(int $gray): self
	{
		return new self(null, $gray, null, null, null);
	}

	/**
	 * Background for truecolor images, with or without alpha.
	 */
	public static function rgb(int $red, int $green, int $blue): self
	{
		return new self(null, null, $red, $green, $blue);
	}

	/**
	 * Space-separated sample values in field order, used by
	 * MetadataDocument::toArray() for the bKGD key.
	 */
	public function toString(): string
	{
		return \implode(' ', \array_filter([
			$this->paletteIndex,
			$this->gray,
			$this->red,
			$this->green,
			$this->blue,
		], static fn (?int $v): bool => $v !== null));
	}
}
