<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\TextEntry;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes tEXt chunks: keyword (1-79 bytes, Latin-1) + text (Latin-1),
 * both converted to UTF-8 as required for interoperable output (spec section 11.3.3.2).
 */
final class TextDecoder implements ChunkDecoderInterface
{
	public function supports(): string
	{
		return 'tEXt';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		$data = $chunk->data();
		$separator = \strpos($data, "\0");

		// Spec requires keyword + null terminator + text (text may be empty);
		// a payload without the terminator is malformed, not keyword-only.
		if ($separator === false) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'tEXt payload has no null terminator.',
			);
		}

		$keyword = \substr($data, 0, $separator);
		if ($keyword === '' || \strlen($keyword) > 79) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('tEXt keyword must be 1-79 bytes, %d given.', \strlen($keyword)),
			);
		}

		$text = \substr($data, $separator + 1);

		$builder->addText(new TextEntry(
			keyword: self::latin1ToUtf8($keyword),
			text: self::latin1ToUtf8($text),
			chunkType: 'tEXt',
		));
	}

	/**
	 * tEXt keywords and text are ISO-8859-1 (Latin-1) per spec; output is UTF-8.
	 */
	public static function latin1ToUtf8(string $value): string
	{
		return \mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
	}
}
