<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes one chunk type into a partial result merged into the metadata document.
 */
interface ChunkDecoderInterface
{
	/**
	 * The 4-byte chunk type code this decoder handles (e.g. 'IHDR').
	 */
	public function supports(): string;

	/**
	 * Decode the chunk. Decoders that depend on earlier chunks read them
	 * from the context; decoders may also enrich the context for later ones.
	 */
	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void;
}
