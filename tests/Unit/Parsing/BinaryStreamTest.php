<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Parsing;

use PNGMetadata\Exception\InvalidPngException;
use PNGMetadata\Parsing\BinaryStream;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryStream::class)]
#[CoversClass(InvalidPngException::class)]
final class BinaryStreamTest extends TestCase
{
	#[Test]
	public function binary_string_is_wrapped_into_a_stream_at_byte_zero(): void
	{
		$stream = BinaryStream::open(PngBuilder::SIGNATURE);
		self::assertIsResource($stream);

		self::assertSame(PngBuilder::SIGNATURE, \stream_get_contents($stream, 8));
		\fclose($stream);
	}

	#[Test]
	public function file_path_is_opened_as_bytes(): void
	{
		$path = \tempnam(\sys_get_temp_dir(), 'pngmeta');
		self::assertIsString($path);
		\file_put_contents($path, PngBuilder::SIGNATURE);

		$stream = BinaryStream::open($path);
		self::assertSame("\x89", \fread($stream, 1));

		\fclose($stream);
		\unlink($path);
	}

	#[Test]
	public function seekable_resource_is_rewound_before_use(): void
	{
		$stream = \fopen('php://temp', 'r+b');
		self::assertIsResource($stream);
		\fwrite($stream, PngBuilder::SIGNATURE);
		\fseek($stream, 10);

		$opened = BinaryStream::open($stream);
		self::assertSame("\x89", \fread($opened, 1));
		\fclose($opened);
	}

	#[Test]
	public function non_seekable_streams_are_buffered_into_temp(): void
	{
		$png = PngBuilder::create()->build();
		$pair = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		self::assertIsArray($pair);
		[$write, $read] = $pair;
		\fwrite($write, $png);
		\fclose($write);

		self::assertFalse(\stream_get_meta_data($read)['seekable']);

		$buffered = BinaryStream::open($read);
		self::assertSame($png, \stream_get_contents($buffered));

		\fclose($read);
		\fclose($buffered);
	}

	#[Test]
	public function non_stream_resources_are_rejected(): void
	{
		$context = \stream_context_create();

		$this->expectException(InvalidPngException::class);
		$this->expectExceptionMessage('not a stream');
		BinaryStream::open($context);
	}

	#[Test]
	public function empty_input_is_rejected(): void
	{
		$this->expectException(InvalidPngException::class);
		BinaryStream::open('');
	}

	#[Test]
	public function non_string_non_resource_input_is_rejected(): void
	{
		$this->expectException(InvalidPngException::class);
		BinaryStream::open(42);
	}
}
