<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Chunk;

use InvalidArgumentException;
use PNGMetadata\Chunk\BoundedInflater;
use PNGMetadata\Exception\ChunkDecodingException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BoundedInflater::class)]
#[CoversClass(ChunkDecodingException::class)]
final class BoundedInflaterTest extends TestCase
{
	#[Test]
	public function the_default_budget_is_sixteen_mebibytes(): void
	{
		self::assertSame(16 * 1024 * 1024, BoundedInflater::DEFAULT_MAX_BYTES);
	}

	#[Test]
	public function it_inflates_a_payload_within_the_budget(): void
	{
		$inflater = new BoundedInflater(10);

		self::assertSame('hello', $inflater->inflate(\gzcompress('hello'), 'zTXt', 0));
	}

	#[Test]
	public function it_accepts_a_payload_at_the_exact_budget(): void
	{
		$inflater = new BoundedInflater(5);

		self::assertSame('hello', $inflater->inflate(\gzcompress('hello'), 'zTXt', 0));
	}

	#[Test]
	public function it_accepts_a_one_byte_budget(): void
	{
		$inflater = new BoundedInflater(1);

		self::assertSame('x', $inflater->inflate(\gzcompress('x'), 'zTXt', 0));
	}

	#[Test]
	public function it_accepts_a_payload_when_one_byte_remains(): void
	{
		$inflater = new BoundedInflater(6);

		self::assertSame('hello', $inflater->inflate(\gzcompress('hello'), 'zTXt', 0));
		self::assertSame('x', $inflater->inflate(\gzcompress('x'), 'iTXt', 0));
	}

	#[Test]
	public function it_rejects_a_payload_above_the_budget(): void
	{
		$inflater = new BoundedInflater(4);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage(
			'Decode failure in chunk "zTXt" at offset 42: zlib inflation failed or exceeded the 4-byte text budget.',
		);

		$inflater->inflate(\gzcompress('hello'), 'zTXt', 42);
	}

	#[Test]
	public function it_rejects_corrupt_zlib(): void
	{
		$inflater = new BoundedInflater(100);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('zlib inflation failed or exceeded the 100-byte text budget');

		$inflater->inflate('not-zlib-data', 'iTXt', 7);
	}

	#[Test]
	public function it_accumulates_consumed_bytes(): void
	{
		$inflater = new BoundedInflater(5);

		self::assertSame('hello', $inflater->inflate(\gzcompress('hello'), 'zTXt', 0));

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('5-byte text budget');

		$inflater->inflate(\gzcompress('x'), 'iTXt', 0);
	}

	#[Test]
	public function it_tracks_bytes_across_successful_inflations(): void
	{
		$inflater = new BoundedInflater(10);

		self::assertSame('12345', $inflater->inflate(\gzcompress('12345'), 'zTXt', 0));
		self::assertSame('1234', $inflater->inflate(\gzcompress('1234'), 'zTXt', 0));
		self::assertSame('1', $inflater->inflate(\gzcompress('1'), 'zTXt', 0));

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('10-byte text budget');

		$inflater->inflate(\gzcompress('x'), 'iTXt', 0);
	}

	#[Test]
	public function it_rejects_a_payload_after_the_budget_is_spent(): void
	{
		$inflater = new BoundedInflater(1);

		self::assertSame('x', $inflater->inflate(\gzcompress('x'), 'zTXt', 0));

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('1-byte text budget');

		$inflater->inflate(\gzcompress('y'), 'zTXt', 12);
	}

	#[Test]
	public function it_rejects_a_zero_budget(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('must be greater than zero');

		new BoundedInflater(0);
	}

	#[Test]
	public function it_rejects_a_negative_budget(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('must be greater than zero');

		new BoundedInflater(-1);
	}
}
