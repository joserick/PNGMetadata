<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * PNG color types (spec section 6.1).
 */
enum ColorType: int
{
	case Grayscale = 0;
	case RGB = 2;
	case Palette = 3;
	case GrayscaleWithAlpha = 4;
	case RGBWithAlpha = 6;

	/**
	 * Human-readable name matching the v1 output values.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Grayscale => 'Grayscale',
			self::RGB => 'RGB',
			self::Palette => 'Palette',
			self::GrayscaleWithAlpha => 'Grayscale with Alpha',
			self::RGBWithAlpha => 'RGB with Alpha',
		};
	}

	/**
	 * Allowed bit depths per color type (spec section 6.1, table of allowed combinations).
	 *
	 * @return int[]
	 */
	public function allowedBitDepths(): array
	{
		return match ($this) {
			self::Grayscale => [1, 2, 4, 8, 16],
			self::RGB, self::GrayscaleWithAlpha, self::RGBWithAlpha => [8, 16],
			self::Palette => [1, 2, 4, 8],
		};
	}

	public function allowsBitDepth(int $bitDepth): bool
	{
		return \in_array($bitDepth, $this->allowedBitDepths(), true);
	}
}
