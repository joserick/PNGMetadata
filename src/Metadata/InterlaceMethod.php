<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * PNG interlace methods (spec section 8).
 */
enum InterlaceMethod: int
{
	case None = 0;
	case Adam7 = 1;

	/**
	 * Human-readable name matching the v1 output values.
	 */
	public function label(): string
	{
		return match ($this) {
			self::None => 'Noninterlaced',
			self::Adam7 => 'Adam7 Interlace',
		};
	}
}
