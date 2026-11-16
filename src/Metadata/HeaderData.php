<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * Decoded IHDR chunk: the image header shared as decoding context.
 */
final readonly class HeaderData
{
	public function __construct(
		public int $width,
		public int $height,
		public int $bitDepth,
		public ColorType $colorType,
		public int $compressionMethod,
		public int $filterMethod,
		public InterlaceMethod $interlaceMethod,
	) {
	}

	/**
	 * Plain-array form of the header; the key names are the public
	 * serialization contract.
	 *
	 * Compression and filter always read as "Deflate/Inflate" and "Adaptive"
	 * because PNG defines only method 0 for both (spec section 11.2.1).
	 *
	 * @return array<string, int|string>
	 */
	public function toArray(): array
	{
		return [
			'ImageWidth' => $this->width,
			'ImageHeight' => $this->height,
			'BitDepth' => $this->bitDepth,
			'ColorType' => $this->colorType->label(),
			'Compression' => 'Deflate/Inflate',
			'Filter' => 'Adaptive',
			'Interlace' => $this->interlaceMethod->label(),
		];
	}
}
