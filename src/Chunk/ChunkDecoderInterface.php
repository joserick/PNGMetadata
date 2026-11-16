<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Parsing\RawChunk;

/**
 * Contract for the one-class-per-chunk-type decoders (ADR-003).
 *
 * Implementations are stateless and reused across files: per-file state lives
 * in DecodingContext (read) and MetadataBuilder (write), never in the decoder.
 * A malformed payload must throw ChunkDecodingException instead of swallowing
 * it - the reader owns the mode-governed decision to omit or rethrow (ADR-007).
 */
interface ChunkDecoderInterface
{
	/**
	 * The 4-byte chunk type code this decoder handles (e.g. 'IHDR').
	 *
	 * This is the registry key, so exactly one decoder may claim a code.
	 */
	public function supports(): string;

	/**
	 * Decode the chunk into the builder.
	 *
	 * Decoders that depend on earlier chunks read them from the context;
	 * decoders may also enrich the context for later ones (IhdrDecoder does).
	 * Stream-shape violations that leave usable data (duplicate singletons,
	 * late eXIf) are mode-aware instead of always throwing, because lenient
	 * mode must still expose the value (ADR-008).
	 */
	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void;
}
