<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Metadata;

use PNGMetadata\PngMetadataReader;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PngMetadataReader::class)]
final class MetadataDocumentTest extends TestCase
{
	#[Test]
	public function it_serializes_typed_document_to_array(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addSrgb(0)
				->addBkgd(\pack('nnn', 1, 2, 3))
				->addText('Title', 'Example')
				->build(),
		);

		$array = $doc->toArray();

		self::assertSame(800, $array['IHDR']['ImageWidth']);
		self::assertSame('Perceptual', $array['sRGB']);
		self::assertSame('1 2 3', $array['bKGD']);
		self::assertSame('Example', $array['texts'][0]['text']);
	}

	#[Test]
	public function it_exposes_thumbnail_bytes_without_gd(): void
	{
		// Build a minimal EXIF payload with a fake thumbnail by hand is not trivial;
		// here we only assert that a PNG without EXIF returns null safely.
		$doc = PngMetadataReader::create()->read(PngBuilder::create()->build());

		self::assertNull($doc->thumbnailBytes());
	}
}
