<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * Decoded tRNS chunk. Exactly one variant is populated, chosen by the IHDR
 * color type: a gray sample, an RGB sample, or palette alpha values.
 */
final readonly class Transparency
{
	/**
	 * @param int[] $alpha Palette alpha values in palette-index order.
	 */
	private function __construct(
		public ?int $gray,
		public ?int $red,
		public ?int $green,
		public ?int $blue,
		public array $alpha,
	) {
	}

	/**
	 * Transparency key for grayscale images.
	 */
	public static function grayscale(int $gray): self
	{
		return new self($gray, null, null, null, []);
	}

	/**
	 * Transparency key for truecolor images.
	 */
	public static function rgb(int $red, int $green, int $blue): self
	{
		return new self(null, $red, $green, $blue, []);
	}

	/**
	 * Palette alpha values in palette-index order.
	 *
	 * @param int[] $alpha
	 */
	public static function palette(array $alpha): self
	{
		return new self(null, null, null, null, \array_values($alpha));
	}

	/**
	 * Serializable structure used by MetadataDocument::toArray().
	 *
	 * @return array<string, int|int[]>
	 */
	public function toArray(): array
	{
		if ($this->gray !== null) {
			return ['gray' => $this->gray];
		}
		if ($this->red !== null) {
			\assert($this->green !== null && $this->blue !== null);

			return ['red' => $this->red, 'green' => $this->green, 'blue' => $this->blue];
		}

		return ['alpha' => $this->alpha];
	}
}
