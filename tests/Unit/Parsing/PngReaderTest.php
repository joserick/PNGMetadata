<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Parsing;

use PNGMetadata\Exception\CrcMismatchException;
use PNGMetadata\Exception\InvalidPngException;
use PNGMetadata\Exception\TruncatedDataException;
use PNGMetadata\Parsing\BinaryStream;
use PNGMetadata\Parsing\ChunkType;
use PNGMetadata\Parsing\PngReader;
use PNGMetadata\Parsing\RawChunk;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PngReader::class)]
#[CoversClass(RawChunk::class)]
#[CoversClass(ChunkType::class)]
#[CoversClass(BinaryStream::class)]
#[CoversClass(InvalidPngException::class)]
#[CoversClass(TruncatedDataException::class)]
#[CoversClass(CrcMismatchException::class)]
final class PngReaderTest extends TestCase
{
	#[Test]
	public function it_iterates_chunks_in_order(): void
	{
		$png = PngBuilder::create()
			->addText('Author', 'Jose')
			->addIdat()
			->build();

		$codes = [];
		foreach (PngReader::create()->chunks($png) as $chunk) {
			$codes[] = $chunk->getCode();
		}

		self::assertSame(['IHDR', 'tEXt', 'IDAT', 'IEND'], $codes);
	}

	#[Test]
	public function it_accepts_path_string_and_stream_inputs(): void
	{
		$png = PngBuilder::create()->addText('Title', 'Café')->build();
		$path = \tempnam(\sys_get_temp_dir(), 'pngmeta');
		self::assertIsString($path);
		\file_put_contents($path, $png);

		$stream = \fopen($path, 'rb');
		self::assertIsResource($stream);

		$fromString = \array_map(static fn ($chunk) => $chunk->getCode(), \iterator_to_array(PngReader::create()->chunks($png), false));
		$fromPath = \array_map(static fn ($chunk) => $chunk->getCode(), \iterator_to_array(PngReader::create()->chunks($path), false));
		$fromStream = \array_map(static fn ($chunk) => $chunk->getCode(), \iterator_to_array(PngReader::create()->chunks($stream), false));
		\fclose($stream);
		\unlink($path);

		self::assertSame($fromString, $fromPath, 'path input must parse identically to a binary string');
		self::assertSame($fromPath, $fromStream, 'stream input must parse identically to a path input');
	}

	#[Test]
	public function it_rejects_invalid_signature(): void
	{
		$this->expectException(InvalidPngException::class);
		\iterator_to_array(PngReader::create()->chunks('GIF89a..........'), false);
	}

	#[Test]
	public function it_rejects_unreadable_path_input(): void
	{
		$this->expectException(InvalidPngException::class);
		\iterator_to_array(PngReader::create()->chunks('/definitely/not/here.png'), false);
	}

	#[Test]
	public function it_rejects_truncated_datastream(): void
	{
		$png = \substr(PngBuilder::create()->addIdat()->build(), 0, 40);

		$this->expectException(TruncatedDataException::class);
		$this->expectExceptionMessage('ended while reading chunk header');
		\iterator_to_array(PngReader::create()->chunks($png), false);
	}

	#[Test]
	public function clean_eof_after_a_complete_chunk_reports_the_missing_iend(): void
	{
		$png = PngBuilder::SIGNATURE
			. PngBuilder::chunk('IHDR', \pack('NNCCCCC', 10, 10, 8, 6, 0, 0, 0));

		$this->expectException(TruncatedDataException::class);
		$this->expectExceptionMessage('ended before the IEND chunk');
		\iterator_to_array(PngReader::create()->chunks($png), false);
	}

	#[Test]
	public function it_requires_ihdr_first(): void
	{
		$png = PngBuilder::SIGNATURE
			. PngBuilder::chunk('tEXt', "a\0b")
			. PngBuilder::chunk('IEND', '');

		$this->expectException(InvalidPngException::class);
		\iterator_to_array(PngReader::create()->chunks($png), false);
	}

	#[Test]
	public function it_fails_strict_mode_on_crc_mismatch(): void
	{
		$png = PngBuilder::create()->addText('Author', 'Jose')->build();
		$pos = \strpos($png, 'Author');
		self::assertNotFalse($pos);
		$corrupt = \substr($png, 0, $pos) . 'Xuthor' . \substr($png, $pos + 6);

		$this->expectException(CrcMismatchException::class);
		\iterator_to_array(PngReader::strict()->chunks($corrupt), false);
	}

	#[Test]
	public function lenient_mode_ignores_crc_mismatch(): void
	{
		$png = PngBuilder::create()->addText('Author', 'Jose')->build();
		$pos = \strpos($png, 'Author');
		self::assertNotFalse($pos);
		$corrupt = \substr($png, 0, $pos) . 'Xuthor' . \substr($png, $pos + 6);

		$codes = \array_map(static fn ($chunk) => $chunk->getCode(), \iterator_to_array(PngReader::create()->chunks($corrupt), false));

		self::assertContains('tEXt', $codes);
	}

	#[Test]
	public function large_idat_payloads_are_lazy(): void
	{
		$png = PngBuilder::create()->addIdat(200000)->addIdat(200000)->build();
		$before = \memory_get_usage();

		$codes = [];
		foreach (PngReader::create()->chunks($png) as $chunk) {
			$codes[] = $chunk->getCode();
		}

		$delta = \memory_get_usage() - $before;
		self::assertSame(['IHDR', 'IDAT', 'IDAT', 'IEND'], $codes);
		self::assertLessThan(100000, $delta);
	}
}
