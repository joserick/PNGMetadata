<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the eXIf chunk: an Exif profile (CIPA DC-008 §4.7.2) without the
 * JPEG APP1 marker and "Exif\0\0" ID code (spec section 11.3.4.5).
 *
 * The payload is validated against the TIFF magic bytes before decoding, and
 * a decoding failure never aborts extraction of the remaining metadata.
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
			return; // invalid profile: EXIF section omitted
		}

		$stream = @\fopen('php://temp', 'r+b');
		if ($stream === false) {
			return;
		}
		\fwrite($stream, $data);
		\rewind($stream);

		$exif = @\exif_read_data($stream, null, true);
		\fclose($stream);

		if (!\is_array($exif) || $exif === []) {
			return; // decoding failed: EXIF section omitted, never fatal
		}

		/** @var array<string, mixed> $exif */
		$builder->setExif($exif, $data);
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
