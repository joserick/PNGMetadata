<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\Gamma;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the gAMA chunk: a four-byte unsigned integer scaled by 100000
 * (spec section 11.3.2.1). A zero gamma is not permitted and follows the
 * unified decode-failure policy.
 */
final class GamaDecoder implements ChunkDecoderInterface
{
	public function supports(): string
	{
		return 'gAMA';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		$data = $chunk->data();

		if (\strlen($data) !== 4) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('gAMA payload must be exactly 4 bytes, %d given.', \strlen($data)),
			);
		}

		$scaled = self::uint32($chunk, $data);
		if ($scaled === 0) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'gAMA value must be greater than zero.',
			);
		}

		$builder->setGamma(new Gamma($scaled), $chunk->getCode(), $chunk->dataOffset);
	}

	private static function uint32(RawChunk $chunk, string $data): int
	{
		$unpacked = \unpack('N', $data);
		if ($unpacked === false) {
			// Defensive: the length guard above makes this unreachable.
			throw ChunkDecodingException::forChunk($chunk->getCode(), $chunk->dataOffset, 'could not unpack the gAMA value.');
		}

		/** @var array{1: int} $unpacked */

		return $unpacked[1];
	}
}
