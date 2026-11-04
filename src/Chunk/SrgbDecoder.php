<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the sRGB chunk: a single byte with the rendering intent
 * (spec section 11.3.2.5). Out-of-range bytes are decode failures: the
 * unified policy omits the intent in lenient mode and reports it in strict.
 */
final class SrgbDecoder implements ChunkDecoderInterface
{
	public function supports(): string
	{
		return 'sRGB';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		$data = $chunk->data();
		if ($data === '') {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'sRGB chunk has no data.',
			);
		}

		$intent = RenderingIntent::fromByte(\ord($data[0]));
		if ($intent === null) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('unknown sRGB rendering intent byte %d.', \ord($data[0])),
			);
		}

		$builder->setRenderingIntent($intent, $chunk->getCode(), $chunk->dataOffset);
	}
}
