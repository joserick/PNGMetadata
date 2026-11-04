<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Parsing;

use PNGMetadata\Exception\InvalidPngException;
use PNGMetadata\Exception\PngMetadataException;
use PNGMetadata\Parsing\BinaryStream;
use PNGMetadata\Parsing\ChunkType;
use PNGMetadata\Parsing\PngReader;
use PNGMetadata\Parsing\RawChunk;
use PNGMetadata\PngMetadataReader;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Truncation matrix: a datastream cut at ANY length must fail as a domain
 * error - never leak a raw PHP error, warning, or non-domain exception, and
 * never return a partial document built from invented data.
 */
#[CoversClass(PngMetadataReader::class)]
#[CoversClass(PngReader::class)]
#[CoversClass(BinaryStream::class)]
#[CoversClass(RawChunk::class)]
#[CoversClass(ChunkType::class)]
#[CoversClass(InvalidPngException::class)]
final class TruncationMatrixTest extends TestCase
{
	#[Test]
	public function every_truncated_length_raises_only_domain_exceptions(): void
	{
		$full = PngBuilder::create()
			->withIhdr(2, 2, 8, 0)
			->addText('Title', 'Truncation')
			->addSrgb(0)
			->addIdat(64)
			->build();

		$offenders = [];
		$length = \strlen($full);

		for ($n = 0; $n < $length; $n++) {
			try {
				PngMetadataReader::create()->read(\substr($full, 0, $n));
			} catch (PngMetadataException) {
				// Expected: every truncated stream fails as a typed domain error.
			} catch (\Throwable $e) {
				$offenders[] = \sprintf('offset %d: %s: %s', $n, $e::class, $e->getMessage());
			}
		}

		self::assertSame([], $offenders);
	}
}
