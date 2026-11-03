<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Metadata\HeaderData;

/**
 * Context passed to chunk decoders: chunks decoded earlier in the stream
 * that later decoders need (e.g., bKGD interpretation depends on IHDR).
 */
final class DecodingContext
{
	public function __construct(
		public ?HeaderData $header = null,
	) {
	}

	public function requireHeader(): HeaderData
	{
		return $this->header ?? throw new \LogicException('IHDR must be decoded before this chunk.');
	}
}
