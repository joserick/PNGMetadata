<?php

declare(strict_types=1);

namespace PNGMetadata;

use PNGMetadata\Chunk\ChunkDecoderRegistry;
use PNGMetadata\Chunk\DecodingContext;
use PNGMetadata\Chunk\MetadataBuilder;
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
		$context = new DecodingContext();
		$builder = new MetadataBuilder();

		foreach ($reader->chunks($input) as $chunk) {
			$decoder = $this->registry->decoderFor($chunk->getCode());
			$decoder?->decode($chunk, $context, $builder);
		}

		return $builder->build();
	}
}
