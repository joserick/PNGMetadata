<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Metadata\BackgroundColor;
use PNGMetadata\Metadata\ColorType;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the bKGD chunk. Its interpretation depends on the IHDR color type
 * (spec section 11.3.4.1): palette index (1 byte) for indexed-color, gray
 * sample (2 bytes) for grayscale, RGB triple (6 bytes) for truecolor.
 */
final class BkgdDecoder implements ChunkDecoderInterface
{
	public function supports(): string
	{
		return 'bKGD';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		$data = $chunk->data();
		$header = $context->requireHeader();

		$background = match ($header->colorType) {
			ColorType::Palette => \strlen($data) >= 1
				? BackgroundColor::paletteIndex(\ord($data[0]))
				: null,
			ColorType::Grayscale, ColorType::GrayscaleWithAlpha => \strlen($data) >= 2
				? BackgroundColor::grayscale(self::uint16($data, 0))
				: null,
			ColorType::RGB, ColorType::RGBWithAlpha => \strlen($data) >= 6
				? BackgroundColor::rgb(
					self::uint16($data, 0),
					self::uint16($data, 2),
					self::uint16($data, 4),
				)
				: null,
		};

		if ($background !== null) {
			$builder->setBackground($background);
		}
	}

	/**
	 * Read a big-endian unsigned 16-bit integer at the given byte offset.
	 */
	private static function uint16(string $data, int $offset): int
	{
		$unpacked = \unpack('n', \substr($data, $offset, 2));
		if ($unpacked === false) {
			throw new \PNGMetadata\Exception\ChunkDecodingException('Could not unpack a bKGD sample.');
		}
		/** @var array{1: int} $unpacked */

		return $unpacked[1];
	}
}
