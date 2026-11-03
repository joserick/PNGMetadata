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
 * length. When compFlag = 1 the text is a zlib datastream.
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

		$keyword = $this->readNullTerminated($data, $offset, $length, 'keyword');

		if ($offset + 2 > $length) {
			throw new ChunkDecodingException('iTXt chunk is missing the compression flag/method bytes.');
		}
		$compressionFlag = \ord($data[$offset]);
		$offset += 2; // flag + method (method is always 0 and ignored for uncompressed text)

		$languageTag = $this->readNullTerminated($data, $offset, $length, 'language tag');
		$translatedKeyword = $this->readNullTerminated($data, $offset, $length, 'translated keyword');

		$text = \substr($data, $offset); // not null-terminated: rest of the chunk

		$compressed = $compressionFlag === 1;
		if ($compressed) {
			$inflated = @\gzuncompress($text);
			if ($inflated === false) {
				throw new ChunkDecodingException('iTXt text is flagged as compressed but zlib inflation failed.');
			}
			$text = $inflated;
		}

		if ($keyword === self::XMP_KEYWORD) {
			$xmp = XmpDecoder::parse($text);
			if ($xmp !== null) {
				$builder->setXmp($xmp);
			}

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

	private function readNullTerminated(string $data, int &$offset, int $length, string $field): string
	{
		$end = \strpos($data, "\0", $offset);
		if ($end === false) {
			throw new ChunkDecodingException(\sprintf('iTXt chunk is missing the null separator after the %s.', $field));
		}
		$value = \substr($data, $offset, $end - $offset);
		$offset = $end + 1;

		return $value;
	}
}
