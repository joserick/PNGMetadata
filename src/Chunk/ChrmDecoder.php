<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\Chromaticities;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the cHRM chunk: eight unsigned integers scaled by 100000 — white
 * point x/y followed by the red, green and blue primary x/y values
 * (spec section 11.3.2.2).
 */
final class ChrmDecoder implements ChunkDecoderInterface
{
	public function supports(): string
	{
		return 'cHRM';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		$data = $chunk->data();

		if (\strlen($data) !== 32) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('cHRM payload must be exactly 32 bytes, %d given.', \strlen($data)),
			);
		}

		$unpacked = \unpack('N8', $data);
		if ($unpacked === false) {
			// Defensive: the length guard above makes this unreachable.
			throw ChunkDecodingException::forChunk($chunk->getCode(), $chunk->dataOffset, 'could not unpack the cHRM coordinates.');
		}

		/** @var array{1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int, 8: int} $unpacked */
		$builder->setChromaticities(
			Chromaticities::fromScaled(
				$unpacked[1],
				$unpacked[2],
				$unpacked[3],
				$unpacked[4],
				$unpacked[5],
				$unpacked[6],
				$unpacked[7],
				$unpacked[8],
			),
			$chunk->getCode(),
			$chunk->dataOffset,
		);
	}
}
