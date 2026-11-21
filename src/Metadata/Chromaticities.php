<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * Decoded cHRM chunk: white point and RGB primary chromaticities, scaled from
 * the eight unsigned integers carried by the chunk (spec section 11.3.2.2).
 */
final readonly class Chromaticities
{
	public function __construct(
		public float $whiteX,
		public float $whiteY,
		public float $redX,
		public float $redY,
		public float $greenX,
		public float $greenY,
		public float $blueX,
		public float $blueY,
	) {
	}

	/**
	 * Build from the eight values as stored in the chunk (each × 100000).
	 */
	public static function fromScaled(
		int $whiteX,
		int $whiteY,
		int $redX,
		int $redY,
		int $greenX,
		int $greenY,
		int $blueX,
		int $blueY,
	): self {
		return new self(
			$whiteX / 100000,
			$whiteY / 100000,
			$redX / 100000,
			$redY / 100000,
			$greenX / 100000,
			$greenY / 100000,
			$blueX / 100000,
			$blueY / 100000,
		);
	}

	/**
	 * Coordinate groups used by MetadataDocument::toArray().
	 *
	 * @return array<string, array{x: float, y: float}>
	 */
	public function toArray(): array
	{
		return [
			'white' => ['x' => $this->whiteX, 'y' => $this->whiteY],
			'red' => ['x' => $this->redX, 'y' => $this->redY],
			'green' => ['x' => $this->greenX, 'y' => $this->greenY],
			'blue' => ['x' => $this->blueX, 'y' => $this->blueY],
		];
	}
}
