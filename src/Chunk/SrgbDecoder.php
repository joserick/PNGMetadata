<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the sRGB chunk: a single byte with the rendering intent
 * (spec section 11.3.2.5). Out-of-range bytes are reported as unknown.
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
			return;
		}
		$intent = RenderingIntent::fromByte(\ord($data[0]));
		if ($intent !== null) {
			$builder->setRenderingIntent($intent);
		}
	}
}
