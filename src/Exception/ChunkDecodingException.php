<?php

declare(strict_types=1);

namespace PNGMetadata\Exception;

use RuntimeException;

/**
 * Thrown when a chunk payload cannot be decoded according to the PNG specification.
 */
class ChunkDecodingException extends RuntimeException implements PngMetadataException
{
	/**
	 * Evidence-bearing failure: the strict-mode contract promises consumers can
	 * tell WHICH chunk failed and WHERE, so type and byte offset live in the
	 * message itself instead of being attached by the catch site.
	 */
	public static function forChunk(string $code, int $offset, string $reason): self
	{
		return new self(\sprintf('Decode failure in chunk "%s" at offset %d: %s', $code, $offset, $reason));
	}
}
