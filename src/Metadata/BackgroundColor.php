<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * Decoded bKGD chunk. Which fields are set depends on the IHDR color type.
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

	public static function paletteIndex(int $index): self
	{
		return new self($index, null, null, null, null);
	}

	public static function grayscale(int $gray): self
	{
		return new self(null, $gray, null, null, null);
	}

	public static function rgb(int $red, int $green, int $blue): self
	{
		return new self(null, null, $red, $green, $blue);
	}

	/**
	 * Space-separated sample values, matching the v1 output format.
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
