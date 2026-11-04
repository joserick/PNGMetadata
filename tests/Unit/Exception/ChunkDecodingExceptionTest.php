<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Exception;

use PNGMetadata\Exception\ChunkDecodingException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChunkDecodingException::class)]
final class ChunkDecodingExceptionTest extends TestCase
{
	#[Test]
	public function forChunk_formats_type_offset_and_reason(): void
	{
		$exception = ChunkDecodingException::forChunk('iTXt', 1234, 'missing null separator');

		self::assertSame(
			'Decode failure in chunk "iTXt" at offset 1234: missing null separator',
			$exception->getMessage(),
		);
	}
}
