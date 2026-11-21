<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use InvalidArgumentException;
use PNGMetadata\Exception\ChunkDecodingException;

/**
 * Enforces a cumulative inflation budget for compressed text chunks.
 *
 * A fresh instance is created per read(), so the budget covers every zTXt and
 * compressed iTXt payload in that read rather than allowing each chunk its own
 * allowance. gzuncompress() is given the remaining bytes as its max_length and
 * the returned length is checked again: PHP's max_length argument is not an
 * exact cap for every payload, and a payload that inflates past the remaining
 * budget must never be accepted.
 */
final class BoundedInflater
{
	/** 16 MiB, far above ordinary text/XMP metadata. */
	public const DEFAULT_MAX_BYTES = 16 * 1024 * 1024;

	private int $inflatedBytes = 0;

	public function __construct(
		private readonly int $maxBytes = self::DEFAULT_MAX_BYTES,
	) {
		if ($maxBytes < 1) {
			throw new InvalidArgumentException('The inflation budget must be greater than zero.');
		}
	}

	/**
	 * Inflate a zlib stream within the remaining budget.
	 *
	 * @throws ChunkDecodingException When the stream is corrupt, exceeds the
	 *                                remaining budget, or the budget is spent.
	 */
	public function inflate(string $data, string $chunkCode, int $offset): string
	{
		$remaining = $this->maxBytes - $this->inflatedBytes;

		if ($remaining < 1) {
			throw $this->failure($chunkCode, $offset);
		}

		$inflated = @\gzuncompress($data, $remaining);

		if ($inflated === false || \strlen($inflated) > $remaining) {
			throw $this->failure($chunkCode, $offset);
		}

		$this->inflatedBytes += \strlen($inflated);

		return $inflated;
	}

	private function failure(string $chunkCode, int $offset): ChunkDecodingException
	{
		return ChunkDecodingException::forChunk(
			$chunkCode,
			$offset,
			\sprintf('zlib inflation failed or exceeded the %d-byte inflation budget.', $this->maxBytes),
		);
	}
}
