<?php

declare(strict_types=1);

namespace PNGMetadata\Parsing;

/**
 * A raw PNG chunk: framing fields plus lazy access to the payload.
 *
 * The payload is not materialized in memory until data() is called, so
 * large image-data chunks (IDAT, fdAT) can be skipped at zero memory cost.
 */
final readonly class RawChunk
{
	/**
	 * @param resource $stream Stream positioned anywhere; data() seeks to
	 *                         $dataOffset before reading.
	 */
	public function __construct(
		public ChunkType $type,
		public int $length,
		public int $dataOffset,
		public int $storedCrc,
		private mixed $stream,
	) {
	}

	public function getCode(): string
	{
		return $this->type->code;
	}

	/**
	 * Read the chunk payload from the underlying stream.
	 *
	 * Position-neutral: the stream cursor is restored after reading, so
	 * sequential iteration is not disturbed by lazy payload access.
	 */
	public function data(): string
	{
		if ($this->length === 0) {
			return '';
		}
		$position = \ftell($this->stream);
		if (\fseek($this->stream, $this->dataOffset, SEEK_SET) !== 0) {
			throw new \PNGMetadata\Exception\TruncatedDataException(
				\sprintf('Cannot seek to data offset of chunk "%s".', $this->type->code),
			);
		}
		$data = \stream_get_contents($this->stream, $this->length);
		if ($position !== false) {
			\fseek($this->stream, $position, SEEK_SET);
		}
		if ($data === false || \strlen($data) < $this->length) {
			throw new \PNGMetadata\Exception\TruncatedDataException(
				\sprintf(
					'Chunk "%s" declares %d bytes but the datastream ended early.',
					$this->type->code,
					$this->length,
				),
			);
		}

		return $data;
	}

	/**
	 * Compute the CRC-32 over type + data, as defined by the PNG spec (section 5.5).
	 */
	public function computeCrc(): int
	{
		return \crc32($this->type->code . $this->data());
	}

	public function isCrcValid(): bool
	{
		return $this->computeCrc() === $this->storedCrc;
	}
}
