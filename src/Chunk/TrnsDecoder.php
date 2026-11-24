<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\ColorType;
use PNGMetadata\Metadata\Transparency;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the tRNS chunk. Its interpretation depends on the IHDR color type
 * (spec section 11.3.4.2): a gray sample for grayscale, an RGB sample for
 * truecolor, or palette alpha values for indexed-color images. The chunk is
 * forbidden when the color type already carries an alpha channel.
 *
 * A missing IHDR context or an unusable payload is a decode failure governed
 * by the unified policy - never a logic crash.
 */
final class TrnsDecoder implements ChunkDecoderInterface
{
	public function supports(): string
	{
		return 'tRNS';
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
				'tRNS cannot be interpreted before the IHDR chunk.',
			);
		}

		$transparency = match ($header->colorType) {
			ColorType::Grayscale => Transparency::grayscale(
				self::fixedSample($chunk, $data, $context, 0, $header->colorType),
			),
			ColorType::RGB => Transparency::rgb(
				self::fixedSample($chunk, $data, $context, 0, $header->colorType),
				self::fixedSample($chunk, $data, $context, 2, $header->colorType),
				self::fixedSample($chunk, $data, $context, 4, $header->colorType),
			),
			ColorType::Palette => Transparency::palette(self::paletteAlpha($chunk, $data)),
			ColorType::GrayscaleWithAlpha, ColorType::RGBWithAlpha => throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('tRNS is not permitted for color type %s.', $header->colorType->label()),
			),
		};

		$builder->setTransparency($transparency, $chunk->getCode(), $chunk->dataOffset);
	}

	/**
	 * Read one fixed-length 16-bit sample for grayscale or truecolor tRNS.
	 */
	private static function fixedSample(
		RawChunk $chunk,
		string $data,
		DecodingContext $context,
		int $offset,
		ColorType $colorType,
	): int {
		$required = $colorType === ColorType::Grayscale ? 2 : 6;

		if (\strlen($data) < $required) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf(
					'tRNS needs at least %d byte(s) for color type %s, %d given.',
					$required,
					$colorType->label(),
					\strlen($data),
				),
			);
		}

		if ($context->strict && \strlen($data) !== $required) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf(
					'tRNS payload must be exactly %d byte(s) for color type %s, %d given.',
					$required,
					$colorType->label(),
					\strlen($data),
				),
			);
		}

		$unpacked = \unpack('n', $data, $offset);
		if ($unpacked === false) {
			// Defensive: the length guard above makes this unreachable.
			throw ChunkDecodingException::forChunk($chunk->getCode(), $chunk->dataOffset, 'could not unpack a tRNS sample.');
		}

		/** @var array{1: int} $unpacked */

		return $unpacked[1];
	}

	/**
	 * @return int[]
	 */
	private static function paletteAlpha(RawChunk $chunk, string $data): array
	{
		$length = \strlen($data);
		if ($length < 1 || $length > 256) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('tRNS palette alpha table must contain 1-256 bytes, %d given.', $length),
			);
		}

		$unpacked = \unpack('C*', $data);
		if ($unpacked === false) {
			// Defensive: the length guard above makes this unreachable.
			throw ChunkDecodingException::forChunk($chunk->getCode(), $chunk->dataOffset, 'could not unpack the tRNS palette alpha table.');
		}

		/** @var array<int, int> $unpacked */

		return $unpacked;
	}
}
