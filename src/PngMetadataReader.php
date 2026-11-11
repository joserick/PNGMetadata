<?php

declare(strict_types=1);

namespace PNGMetadata;

use PNGMetadata\Chunk\ChunkDecoderRegistry;
use PNGMetadata\Chunk\DecodingContext;
use PNGMetadata\Chunk\MetadataBuilder;
use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\MetadataDocument;
use PNGMetadata\Parsing\PngReader;

/**
 * Single public entry point of the core: PNG input → typed metadata document.
 */
final class PngMetadataReader
{
	public function __construct(
		private readonly ChunkDecoderRegistry $registry = new ChunkDecoderRegistry(),
		private readonly bool $strict = false,
	) {
	}

	public static function create(bool $strict = false): self
	{
		return new self(strict: $strict);
	}

	/**
	 * @param string|resource $input File path, binary string, or stream resource.
	 */
	public function read(mixed $input): MetadataDocument
	{
		$reader = PngReader::create($this->strict);
		$context = new DecodingContext(strict: $this->strict);
		$builder = new MetadataBuilder($this->strict);
		$isFirstChunk = true;

		foreach ($reader->chunks($input) as $chunk) {
			$isHeaderChunk = $isFirstChunk;
			$isFirstChunk = false;

			if ($chunk->type->equals('IDAT') || $chunk->type->equals('fdAT')) {
				// PNG 3rd ed. expects eXIf before image data; note the position
				// before dispatching so later chunks can enforce it in strict mode.
				$context->noteImageData();
			}

			$decoder = $this->registry->decoderFor($chunk->getCode());
			if ($decoder === null) {
				// Unknown ancillary chunks are never an error (spec §5.4).
				// Unknown *critical* chunks are a conformance failure: strict
				// reports them (spec §5.4 makes them an error for consumers),
				// lenient keeps ignoring them.
				if ($this->strict && $chunk->type->isCritical() && !$chunk->type->isRecognizedCritical()) {
					throw ChunkDecodingException::forChunk(
						$chunk->getCode(),
						$chunk->dataOffset,
						\sprintf(
							'unknown critical chunk type "%s"; recognized critical types are IHDR, PLTE, IDAT, IEND, fdAT.',
							$chunk->getCode(),
						),
					);
				}

				continue;
			}

			try {
				$decoder->decode($chunk, $context, $builder);
			} catch (ChunkDecodingException $e) {
				// Unified failure policy: strict mode reports every violation;
				// lenient mode omits the chunk and keeps extracting - except for
				// the IHDR that establishes the context (the first chunk), whose
				// failure is fatal because later chunks lose interpretation.
				// Duplicated IHDRs later in the stream are policy-governed
				// (spec §11.2.1: IHDR occurs exactly once, at the start).
				if ($this->strict || ($isHeaderChunk && $chunk->type->equals('IHDR'))) {
					throw $e;
				}
			}
		}

		return $builder->build();
	}
}
