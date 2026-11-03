<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit;

use PNGMetadata\PNGMetadata;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PNGMetadata::class)]
final class PNGMetadataTest extends TestCase
{
	private string $path = '';

	protected function setUp(): void
	{
		$png = PngBuilder::create()
			->withIhdr(120, 80, 8, 2)
			->addText('exif:DateTime', '2026:08:11 10:00:00')
			->addText('exif:thumbnail:Compression', '6')
			->addSrgb(0)
			->build();

		$path = \tempnam(\sys_get_temp_dir(), 'pngmeta');
		self::assertIsString($path);
		\file_put_contents($path, $png);
		$this->path = $path;
	}

	protected function tearDown(): void
	{
		if ($this->path !== '' && \file_exists($this->path)) {
			\unlink($this->path);
		}
	}

	#[Test]
	public function it_preserves_v1_array_shape_and_access(): void
	{
		$metadata = new PNGMetadata($this->path);

		self::assertSame(120, $metadata->get('IHDR:ImageWidth'));
		self::assertSame('2026:08:11 10:00:00', $metadata->get('exif:DateTime'));
		self::assertSame('6', $metadata->get('exif:THUMBNAIL:Compression'));
		self::assertNull($metadata->get('exif:DoesNotExist'));
	}

	#[Test]
	public function it_iterates_as_array_object(): void
	{
		$metadata = new PNGMetadata($this->path);
		$keys = [];
		foreach ($metadata as $key => $value) {
			$keys[] = $key;
		}

		self::assertContains('IHDR', $keys);
		self::assertContains('exif', $keys);
	}

	#[Test]
	public function extract_returns_null_on_failure(): void
	{
		self::assertNull(PNGMetadata::extract('/definitely/not/here.png'));
	}

	#[Test]
	public function it_renders_two_column_output(): void
	{
		$output = (string) new PNGMetadata($this->path);

		self::assertStringContainsString('--Metadata--', $output);
		self::assertStringContainsString('IHDR:ImageWidth', $output);
		self::assertStringContainsString('120', $output);
	}

	#[Test]
	public function it_preserves_v1_type_helpers_and_codes(): void
	{
		self::assertTrue(PNGMetadata::isPNG($this->path));
		self::assertSame(IMAGETYPE_PNG, PNGMetadata::getType($this->path));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionCode(102);
		PNGMetadata::getType('/missing.png');
	}

	#[Test]
	public function get_thumbnail_returns_false_without_exif(): void
	{
		$metadata = new PNGMetadata($this->path);

		self::assertFalse($metadata->getThumbnail());
	}
}
