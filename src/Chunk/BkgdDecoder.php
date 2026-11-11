<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\BackgroundColor;
use PNGMetadata\Metadata\ColorType;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the bKGD chunk. Its interpretation depends on the IHDR color type
 * (spec section 11.3.4.1): palette index (1 byte) for indexed-color, gray
 * sample (2 bytes) for grayscale, or RGB triple (6 bytes) for truecolor.
 *
 * A missing IHDR context or a short payload is a decode failure governed by
 * the unified policy - never a logic crash.
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
		$header = $context->header;
		if ($header === null) {
			// Explicit check instead of requireHeader(): a missing header here is
			// a policy-governed decode failure, not a programming-error crash.
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'bKGD cannot be interpreted before the IHDR chunk.',
			);
		}

		$required = match ($header->colorType) {
			ColorType::Palette => 1,
			ColorType::Grayscale, ColorType::GrayscaleWithAlpha => 2,
			ColorType::RGB, ColorType::RGBWithAlpha => 6,
		};

		if (\strlen($data) < $required) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf(
					'bKGD needs at least %d byte(s) for color type %s, %d given.',
					$required,
					$header->colorType->label(),
					\strlen($data),
				),
			);
		}

		if ($context->strict && \strlen($data) !== $required) {
			// Spec §11.3.4.1 fixes the payload size per color type: extra
			// trailing bytes are a conformance failure in strict mode, while
			// lenient keeps tolerating them (best-effort leading samples).
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf(
					'bKGD payload must be exactly %d byte(s) for color type %s, %d given.',
					$required,
					$header->colorType->label(),
					\strlen($data),
				),
			);
		}

		$background = match ($header->colorType) {
			ColorType::Palette => BackgroundColor::paletteIndex(\ord($data[0])),
			ColorType::Grayscale, ColorType::GrayscaleWithAlpha => BackgroundColor::grayscale(self::uint16($chunk, $data, 0)),
			ColorType::RGB, ColorType::RGBWithAlpha => BackgroundColor::rgb(
				self::uint16($chunk, $data, 0),
				self::uint16($chunk, $data, 2),
				self::uint16($chunk, $data, 4),
			),
		};

		$builder->setBackground($background, $chunk->getCode(), $chunk->dataOffset);
	}

	/**
	 * Read a big-endian unsigned 16-bit integer at the given byte offset.
	 */
	private static function uint16(RawChunk $chunk, string $data, int $offset): int
	{
		$unpacked = \unpack('n', \substr($data, $offset, 2));
		if ($unpacked === false) {
			// Defensive: the length guard above makes this unreachable, but the
			// failure stays in the domain family if a future caller loosens it.
			throw ChunkDecodingException::forChunk($chunk->getCode(), $chunk->dataOffset, 'could not unpack a bKGD sample.');
		}
		/** @var array{1: int} $unpacked */

		return $unpacked[1];
	}
}
