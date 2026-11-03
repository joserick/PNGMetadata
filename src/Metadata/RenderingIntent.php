<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * ICC rendering intents carried by the sRGB chunk (spec section 11.3.2.5).
 */
enum RenderingIntent: int
{
	case Perceptual = 0;
	case RelativeColorimetric = 1;
	case Saturation = 2;
	case AbsoluteColorimetric = 3;

	/**
	 * Human-readable name matching the v1 output values.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Perceptual => 'Perceptual',
			self::RelativeColorimetric => 'Relative Colorimetric',
			self::Saturation => 'Saturation',
			self::AbsoluteColorimetric => 'Absolute Colorimetric',
		};
	}

	/**
	 * Resolve a raw byte; out-of-range values yield null (reported as "Unknown").
	 */
	public static function fromByte(int $byte): ?self
	{
		return self::tryFrom($byte);
	}
}
