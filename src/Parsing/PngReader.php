<?php

declare(strict_types=1);

namespace PNGMetadata\Parsing;

use Generator;
use PNGMetadata\Exception\CrcMismatchException;
use PNGMetadata\Exception\InvalidPngException;
use PNGMetadata\Exception\TruncatedDataException;

/**
 * Reads a PNG datastream and yields its chunks as RawChunk value objects.
 *
 * Chunk payloads are read lazily (see RawChunk), so iterating the structure
 * of a large image costs memory proportional to the chunk headers only.
 */
final class PngReader
{
	private const SIGNATURE = "\x89PNG\x0d\x0a\x1a\x0a";

	private function __construct(
		private readonly bool $strict,
	) {
	}

	public static function create(bool $strict = false): self
	{
		return new self($strict);
	}

	public static function strict(): self
	{
		return new self(true);
	}

	/**
	 * Iterate over the chunks of a PNG datastream.
	 *
	 * @param string|resource $input File path, binary string, or stream resource.
	 * @return Generator<int, RawChunk> Chunks in file order, ending with IEND.
	 *
	 * @throws InvalidPngException If the input is not a PNG datastream.
	 * @throws TruncatedDataException If the datastream ends mid-chunk.
	 * @throws CrcMismatchException In strict mode, when a chunk CRC does not match.
	 */
	public function chunks(mixed $input): Generator
	{
		$stream = BinaryStream::open($input);
		$ownsStream = !\is_resource($input);

		try {
			$this->assertSignature($stream);
			$isFirst = true;

			while (true) {
				$chunk = $this->readChunk($stream);
				if ($chunk === null) {
					throw new TruncatedDataException('The datastream ended before the IEND chunk.');
				}

				if ($isFirst) {
					$this->assertFirstChunk($chunk);
					$isFirst = false;
				}

				if ($this->strict && !$chunk->isCrcValid()) {
					throw new CrcMismatchException(\sprintf(
						'CRC mismatch in chunk "%s" at offset %d.',
						$chunk->getCode(),
						$chunk->dataOffset,
					));
				}

				yield $chunk;

				if ($chunk->type->equals('IEND')) {
					return;
				}
			}
		} finally {
			if ($ownsStream) {
				\fclose($stream);
			}
		}
	}

	/**
	 * @param resource $stream
	 */
	private function assertSignature(mixed $stream): void
	{
		$signature = $this->readBytes($stream, 8, 'signature');
		if ($signature !== self::SIGNATURE) {
			throw new InvalidPngException('The input does not contain a PNG datastream (invalid signature).');
		}
	}

	private function assertFirstChunk(RawChunk $chunk): void
	{
		if (!$chunk->type->equals('IHDR')) {
			throw new InvalidPngException(\sprintf(
				'The first chunk must be IHDR, "%s" found.',
				$chunk->getCode(),
			));
		}
	}

	/**
	 * @param resource $stream
	 */
	private function readChunk(mixed $stream): ?RawChunk
	{
		$header = $this->readBytes($stream, 8, 'chunk header', allowEof: true);
		if ($header === null) {
			return null;
		}

		$unpacked = \unpack('Nlength/a4type', $header);
		if ($unpacked === false) {
			throw new TruncatedDataException('Could not unpack the chunk header.');
		}
		/** @var array{length: int, type: string} $unpacked */

		$length = $unpacked['length'];
		$type = ChunkType::fromBytes($unpacked['type']);
		$dataOffset = $this->tell($stream);

		// Skip payload + CRC; the payload itself stays lazily readable.
		$this->skip($stream, $length);
		$crcBytes = $this->readBytes($stream, 4, \sprintf('CRC of chunk "%s"', $type->code));
		$crcUnpacked = \unpack('N', $crcBytes ?? '');
		if ($crcUnpacked === false) {
			throw new TruncatedDataException(\sprintf('Could not unpack the CRC of chunk "%s".', $type->code));
		}
		/** @var array{1: int} $crcUnpacked */

		return new RawChunk($type, $length, $dataOffset, $crcUnpacked[1], $stream);
	}

	/**
	 * @param resource $stream
	 * @return string|null Null only when allowEof is set and the stream is exhausted.
	 */
	private function readBytes(mixed $stream, int $count, string $what, bool $allowEof = false): ?string
	{
		if ($count === 0) {
			return '';
		}
		$data = \stream_get_contents($stream, $count);
		if ($data === false || \strlen($data) < $count) {
			if ($allowEof && ($data === false || $data === '')) {
				return null;
			}

			throw new TruncatedDataException(\sprintf(
				'The datastream ended while reading %s (%d bytes expected).',
				$what,
				$count,
			));
		}

		return $data;
	}

	/**
	 * @param resource $stream
	 */
	private function skip(mixed $stream, int $count): void
	{
		if ($count === 0) {
			return;
		}
		if (\fseek($stream, $count, SEEK_CUR) !== 0) {
			throw new TruncatedDataException(\sprintf('Could not skip %d bytes.', $count));
		}
	}

	/**
	 * @param resource $stream
	 */
	private function tell(mixed $stream): int
	{
		$position = \ftell($stream);
		if ($position === false) {
			throw new TruncatedDataException('Could not determine the stream position.');
		}

		return $position;
	}
}
