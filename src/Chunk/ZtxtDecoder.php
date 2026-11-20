<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\TextEntry;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes zTXt chunks: keyword (1-79 bytes, Latin-1), compression method
 * (only 0 = zlib) and zlib-compressed text (Latin-1), both converted to
 * UTF-8 on output (spec section 11.3.3.3).
 *
 * Every malformed shape raises ChunkDecodingException; the reader applies the
 * unified failure policy (lenient omits, strict reports).
 */
final class ZtxtDecoder implements ChunkDecoderInterface
{
	public function supports(): string
	{
		return 'zTXt';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		$data = $chunk->data();
		$separator = \strpos($data, "\0");

		// Spec requires keyword + null terminator + method + compressed text.
		if ($separator === false) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'zTXt payload has no null terminator.',
			);
		}

		$keyword = \substr($data, 0, $separator);
		if ($keyword === '' || \strlen($keyword) > 79) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('zTXt keyword must be 1-79 bytes, %d given.', \strlen($keyword)),
			);
		}

		$methodOffset = $separator + 1;
		if ($methodOffset >= \strlen($data)) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'zTXt chunk is missing the compression method byte.',
			);
		}

		$method = \ord($data[$methodOffset]);
		if ($method !== 0) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('zTXt compression method must be 0 (zlib), %d given.', $method),
			);
		}

		// Compression applies to the Latin-1 bytes, so inflate before charset
		// conversion; the text is the rest of the chunk.
		$inflated = @\gzuncompress(\substr($data, $methodOffset + 1));
		if ($inflated === false) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'zTXt text is compressed with zlib but inflation failed.',
			);
		}

		$builder->addText(new TextEntry(
			keyword: TextDecoder::latin1ToUtf8($keyword),
			text: TextDecoder::latin1ToUtf8($inflated),
			compressed: true,
			chunkType: 'zTXt',
		));
	}
}
