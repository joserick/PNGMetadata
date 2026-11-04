<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Parsing;

use PNGMetadata\Exception\TruncatedDataException;
use PNGMetadata\Parsing\ChunkType;
use PNGMetadata\Parsing\RawChunk;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RawChunk::class)]
#[CoversClass(ChunkType::class)]
#[CoversClass(TruncatedDataException::class)]
final class RawChunkTest extends TestCase
{
	private const PAYLOAD = "A\0b";

	#[Test]
	public function data_restores_the_stream_cursor(): void
	{
		$stream = \fopen('php://temp', 'r+b');
		self::assertIsResource($stream);
		\fwrite($stream, PngBuilder::chunk('tEXt', self::PAYLOAD));
		\fseek($stream, 2);

		$chunk = $this->rawChunk(self::PAYLOAD, $stream);

		self::assertSame(self::PAYLOAD, $chunk->data());
		self::assertSame(2, \ftell($stream)); // position-neutral: cursor back where it was

		\fclose($stream);
	}

	#[Test]
	public function zero_length_payload_reads_as_empty_string(): void
	{
		$stream = \fopen('php://temp', 'r+b');
		self::assertIsResource($stream);

		$chunk = new RawChunk(ChunkType::fromBytes('tEXt'), 0, 5, 0, $stream);

		self::assertSame('', $chunk->data());
		\fclose($stream);
	}

	#[Test]
	public function short_stream_raises_truncated(): void
	{
		$stream = \fopen('php://temp', 'r+b');
		self::assertIsResource($stream);
		\fwrite($stream, 'abc');
		\rewind($stream);

		$chunk = new RawChunk(ChunkType::fromBytes('tEXt'), 10, 0, 0, $stream);

		$this->expectException(TruncatedDataException::class);
		$chunk->data();
	}

	#[Test]
	public function crc_agrees_with_the_builder_framing(): void
	{
		$chunkBytes = PngBuilder::chunk('tEXt', self::PAYLOAD);
		$storedCrc = (int) \unpack('N', \substr($chunkBytes, -4))[1];

		$stream = \fopen('php://temp', 'r+b');
		self::assertIsResource($stream);
		\fwrite($stream, $chunkBytes);
		\rewind($stream);

		$chunk = new RawChunk(ChunkType::fromBytes('tEXt'), \strlen(self::PAYLOAD), 8, $storedCrc, $stream);
		self::assertTrue($chunk->isCrcValid());
		self::assertSame($storedCrc, $chunk->computeCrc());
		self::assertSame('tEXt', $chunk->getCode());

		$tampered = new RawChunk(ChunkType::fromBytes('tEXt'), \strlen(self::PAYLOAD), 8, $storedCrc + 1, $stream);
		self::assertFalse($tampered->isCrcValid());

		\fclose($stream);
	}

	/**
	 * @param resource $stream
	 */
	private function rawChunk(string $payload, mixed $stream): RawChunk
	{
		$framing = PngBuilder::chunk('tEXt', $payload);
		$storedCrc = (int) \unpack('N', \substr($framing, -4))[1];

		return new RawChunk(ChunkType::fromBytes('tEXt'), \strlen($payload), 8, $storedCrc, $stream);
	}
}
