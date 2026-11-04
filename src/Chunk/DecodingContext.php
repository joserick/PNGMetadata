<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\HeaderData;

/**
 * Context passed to chunk decoders: chunks decoded earlier in the stream
 * that later decoders need (e.g., bKGD interpretation depends on IHDR),
 * plus stream-position facts (image data seen) and the reader mode.
 */
final class DecodingContext
{
	private bool $sawImageData = false;

	public function __construct(
		public ?HeaderData $header = null,
		public readonly bool $strict = false,
	) {
	}

	public function requireHeader(): HeaderData
	{
		// Domain exception (not a logic crash): a missing IHDR here means the
		// header failed to decode, so the unified failure policy governs the
		// dependent chunk instead of aborting the read.
		return $this->header ?? throw new ChunkDecodingException('IHDR must be decoded before this chunk.');
	}

	public function noteImageData(): void
	{
		$this->sawImageData = true;
	}

	/**
	 * PNG Third Edition places eXIf before the image data (§11.3.4.5); later
	 * chunks need this fact for the strict-mode placement rule.
	 */
	public function sawImageData(): bool
	{
		return $this->sawImageData;
	}
}
