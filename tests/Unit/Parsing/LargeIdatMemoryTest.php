<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Parsing;

use PNGMetadata\PngMetadataReader;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Spec scenario (png-parsing): a 50 MB IDAT must be iterable without being
 * materialized. Kept out of the default run via the `performance` group;
 * `composer test:performance` executes it explicitly.
 */
#[CoversClass(PngMetadataReader::class)]
#[UsesClass(\PNGMetadata\Parsing\PngReader::class)]
#[UsesClass(\PNGMetadata\Parsing\BinaryStream::class)]
#[UsesClass(\PNGMetadata\Parsing\RawChunk::class)]
final class LargeIdatMemoryTest extends TestCase
{
	private const IDAT_SIZE = 50_000_000;

	#[Test]
	#[Group('performance')]
	public function fifty_megabyte_idat_iterates_within_memory_bounds(): void
	{
		// The fixture is allocated OUTSIDE the measured window on purpose:
		// the assertion is about the reader's incremental cost, not the string.
		$png = PngBuilder::create()->addIdat(self::IDAT_SIZE)->build();

		\memory_reset_peak_usage();
		$document = PngMetadataReader::create()->read($png);
		$peak = \memory_get_peak_usage(true);

		self::assertNotNull($document->header);
		// Reader overhead must stay tiny compared to the payload it skipped:
		// allow the fixture itself plus a generous 14 MB headroom.
		self::assertLessThan(self::IDAT_SIZE + 14 * 1024 * 1024, $peak, 'IDAT payload leaked into memory');
	}
}
