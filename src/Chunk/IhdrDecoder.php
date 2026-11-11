<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\ColorType;
use PNGMetadata\Metadata\HeaderData;
use PNGMetadata\Metadata\InterlaceMethod;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes the IHDR chunk (13 bytes): dimensions, bit depth, color type,
 * compression, filter and interlace methods (spec section 11.2.1).
 */
final class IhdrDecoder implements ChunkDecoderInterface
{
	public function supports(): string
	{
		return 'IHDR';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		if ($context->header !== null) {
			// IHDR occurs exactly once, as the first chunk (spec §11.2.1): a
			// duplicate anywhere else is a policy-governed decode failure -
			// lenient keeps the original header, strict reports the violation.
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'only one IHDR chunk is allowed; the first instance wins.',
			);
		}

		$data = $chunk->data();
		if (\strlen($data) !== 13) {
			throw new ChunkDecodingException(\sprintf('IHDR must be 13 bytes, %d given.', \strlen($data)));
		}

		$fields = \unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', $data);
		if ($fields === false) {
			throw new ChunkDecodingException('Could not unpack the IHDR chunk.');
		}
		/** @var array{width: int, height: int, bitDepth: int, colorType: int, compression: int, filter: int, interlace: int} $fields */

		// Spec §11.2.1: dimensions must be non-zero and both methods are 0 for
		// every conforming image. Like every IHDR failure these are fatal in
		// both modes: a nonsense header is not usable interpretation context.
		if ($fields['width'] === 0) {
			throw new ChunkDecodingException('Invalid IHDR: width must be greater than 0.');
		}
		if ($fields['height'] === 0) {
			throw new ChunkDecodingException('Invalid IHDR: height must be greater than 0.');
		}

		$colorType = ColorType::tryFrom($fields['colorType'])
			?? throw new ChunkDecodingException(\sprintf('Unknown IHDR color type %d.', $fields['colorType']));

		if (!$colorType->allowsBitDepth($fields['bitDepth'])) {
			throw new ChunkDecodingException(\sprintf(
				'Invalid IHDR combination: bit depth %d is not allowed for color type %d (%s).',
				$fields['bitDepth'],
				$fields['colorType'],
				$colorType->label(),
			));
		}

		if ($fields['compression'] !== 0) {
			throw new ChunkDecodingException(\sprintf('Invalid IHDR: compression method must be 0, %d given.', $fields['compression']));
		}
		if ($fields['filter'] !== 0) {
			throw new ChunkDecodingException(\sprintf('Invalid IHDR: filter method must be 0, %d given.', $fields['filter']));
		}

		$interlace = InterlaceMethod::tryFrom($fields['interlace'])
			?? throw new ChunkDecodingException(\sprintf('Unknown IHDR interlace method %d.', $fields['interlace']));

		$header = new HeaderData(
			width: $fields['width'],
			height: $fields['height'],
			bitDepth: $fields['bitDepth'],
			colorType: $colorType,
			compressionMethod: $fields['compression'],
			filterMethod: $fields['filter'],
			interlaceMethod: $interlace,
		);

		$context->header = $header;
		$builder->setHeader($header);
	}
}
