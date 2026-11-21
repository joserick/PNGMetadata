<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * Decoded gAMA chunk: the image gamma encoded as an unsigned integer scaled by
 * 100000 (spec section 11.3.2.1).
 */
final readonly class Gamma
{
	public function __construct(
		public int $scaled,
	) {
	}

	/**
	 * Gamma as a decimal value (e.g. 45455 → 0.45455).
	 */
	public function value(): float
	{
		return $this->scaled / 100000;
	}
}
