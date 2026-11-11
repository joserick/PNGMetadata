<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\TextEntry;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes iTXt chunks positionally (spec section 11.3.3.4):
 *   keyword \0 compFlag(1) compMethod(1) langTag \0 translatedKeyword \0 text
 *
 * The text field is NOT null-terminated; its length derives from the chunk
 * length. When compFlag = 1 the text is a zlib datastream. All malformed
 * shapes raise ChunkDecodingException; the reader applies the unified
 * failure policy (lenient omits, strict reports).
 */
final class ItxtDecoder implements ChunkDecoderInterface
{
	/** iTXt keyword carrying an XMP packet. */
	public const XMP_KEYWORD = 'XML:com.adobe.xmp';

	public function supports(): string
	{
		return 'iTXt';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		$data = $chunk->data();
		$offset = 0;
		$length = \strlen($data);

		$keyword = $this->readNullTerminated($data, $offset, $chunk, 'keyword');
		if ($keyword === '' || \strlen($keyword) > 79) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('iTXt keyword must be 1-79 bytes, %d given.', \strlen($keyword)),
			);
		}

		if ($offset + 2 > $length) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'iTXt chunk is missing the compression flag/method bytes.',
			);
		}
		$compressionFlag = \ord($data[$offset]);
		$compressionMethod = \ord($data[$offset + 1]);
		$offset += 2; // flag + method (method is always 0 and ignored for uncompressed text)

		// Spec §11.3.3.4: the flag is 0 or 1, and a flag of 1 defines only
		// method 0. Lenient keeps decoding today (flag != 1 means uncompressed);
		// out-of-domain values are reported only in strict mode.
		if ($context->strict && ($compressionFlag > 1 || ($compressionFlag === 1 && $compressionMethod !== 0))) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('iTXt compression fields are out of domain: flag %d, method %d.', $compressionFlag, $compressionMethod),
			);
		}

		$languageTag = $this->readNullTerminated($data, $offset, $chunk, 'language tag');
		$translatedKeyword = $this->readNullTerminated($data, $offset, $chunk, 'translated keyword');

		$text = \substr($data, $offset); // not null-terminated: rest of the chunk

		$compressed = $compressionFlag === 1;
		if ($compressed) {
			$inflated = @\gzuncompress($text);
			if ($inflated === false) {
				throw ChunkDecodingException::forChunk(
					$chunk->getCode(),
					$chunk->dataOffset,
					'iTXt text is flagged as compressed but zlib inflation failed.',
				);
			}
			$text = $inflated;
		}

		if ($keyword === self::XMP_KEYWORD) {
			// A well-formed packet claims the singleton slot even without
			// properties; malformed XML throws first, leaving the slot free
			// for a later recoverable packet under the lenient policy.
			$builder->setXmp(
				XmpDecoder::parse($text, $chunk->getCode(), $chunk->dataOffset),
				$chunk->getCode(),
				$chunk->dataOffset,
			);

			return;
		}

		$builder->addText(new TextEntry(
			keyword: $keyword,
			text: $text,
			languageTag: $languageTag === '' ? null : $languageTag,
			translatedKeyword: $translatedKeyword === '' ? null : $translatedKeyword,
			compressed: $compressed,
			chunkType: 'iTXt',
		));
	}

	private function readNullTerminated(string $data, int &$offset, RawChunk $chunk, string $field): string
	{
		$end = \strpos($data, "\0", $offset);
		if ($end === false) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('iTXt chunk is missing the null separator after the %s.', $field),
			);
		}
		$value = \substr($data, $offset, $end - $offset);
		$offset = $end + 1;

		return $value;
	}
}
