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

		foreach ($reader->chunks($input) as $chunk) {
			if ($chunk->type->equals('IDAT') || $chunk->type->equals('fdAT')) {
				// PNG 3rd ed. expects eXIf before image data; note the position
				// before dispatching so later chunks can enforce it in strict mode.
				$context->noteImageData();
			}

			$decoder = $this->registry->decoderFor($chunk->getCode());
			if ($decoder === null) {
				continue; // unknown chunks are never an error (spec, ancillary rule)
			}

			try {
				$decoder->decode($chunk, $context, $builder);
			} catch (ChunkDecodingException $e) {
				// Unified failure policy: strict mode reports every violation;
				// lenient mode omits the chunk and keeps extracting - except for
				// IHDR, whose failure is fatal because later chunks lose context.
				if ($this->strict || $chunk->type->equals('IHDR')) {
					throw $e;
				}
			}
		}

		return $builder->build();
	}
}
