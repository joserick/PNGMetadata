<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the eXIf chunk: an Exif profile (CIPA DC-008 §4.7.2) without the
 * JPEG APP1 marker and "Exif\0\0" ID code (spec section 11.3.4.5).
 *
 * The payload is validated against the TIFF magic bytes before decoding.
 * Every failure raises ChunkDecodingException; the reader's unified policy
 * omits the section in lenient mode (net behavior unchanged from before) and
 * reports it in strict mode.
 */
final class ExifDecoder implements ChunkDecoderInterface
{
	private const MAGIC_LE = "II\x2A\x00"; // little-endian (Intel)
	private const MAGIC_BE = "MM\x00\x2A"; // big-endian (Motorola)

	public function supports(): string
	{
		return 'eXIf';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		$data = $chunk->data();

		if (!self::hasValidMagic($data)) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'invalid TIFF header: expected "II*\\0" or "MM\\0*" magic bytes (spec §11.3.4.5.2).',
			);
		}

		if ($context->strict && $context->sawImageData()) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'eXIf must appear before the first IDAT chunk (PNG Third Edition, §11.3.4.5).',
			);
		}

		$stream = @\fopen('php://temp', 'r+b');
		if ($stream === false) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'could not allocate a temporary stream for the Exif profile.',
			);
		}
		\fwrite($stream, $data);
		\rewind($stream);

		$exif = @\exif_read_data($stream, null, true);
		\fclose($stream);

		if (!\is_array($exif) || $exif === []) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'the Exif profile could not be decoded.',
			);
		}

		/** @var array<string, mixed> $exif */
		$builder->setExif($exif, $data, $chunk->getCode(), $chunk->dataOffset);
	}

	/**
	 * The first four bytes must be "II*\0" or "MM\0*" (spec §11.3.4.5.2).
	 */
	public static function hasValidMagic(string $data): bool
	{
		return \strncmp($data, self::MAGIC_LE, 4) === 0
			|| \strncmp($data, self::MAGIC_BE, 4) === 0;
	}
}
