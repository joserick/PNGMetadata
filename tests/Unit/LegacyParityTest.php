<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit;

use PNGMetadata\PNGMetadata;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Parity with the v1 output shape.
 *
 * The expected structures below were captured from the v1 implementation
 * (git HEAD:src/PNGMetadata.php) over a deterministic fixture corpus. Any
 * change in keys, values, or ordering is a breaking change for consumers.
 */
#[CoversClass(PNGMetadata::class)]
final class LegacyParityTest extends TestCase
{
	private string $path = '';

	protected function setUp(): void
	{
		$png = PngBuilder::create()
			->withIhdr(800, 600, 8, 6)
			->addSrgb(0)
			->addBkgd(\pack('nnn', 100, 200, 300))
			->addText('exif:DateTime', '2026:08:11 10:00:00')
			->addText('exif:Make', 'SONY')
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
	public function to_array_matches_v1_golden_structure(): void
	{
		// v1 golden: IHDR never included the Interlace key (it read 6 of 7 fields).
		$expected = [
			'IHDR' => [
				'ImageWidth' => 800,
				'ImageHeight' => 600,
				'BitDepth' => 8,
				'ColorType' => 'RGB with Alpha',
				'Compression' => 'Deflate/Inflate',
				'Filter' => 'Adaptive',
			],
			'bKGD' => '100 200 300',
			'exif' => [
				'DateTime' => '2026:08:11 10:00:00',
				'Make' => 'SONY',
			],
			'sRGB' => 'Perceptual',
		];

		$actual = (new PNGMetadata($this->path))->toArray();

		self::assertSame($expected, $actual, 'The legacy array shape diverged from v1.');
	}

	#[Test]
	public function legacy_top_level_keys_are_ksorted(): void
	{
		$keys = \array_keys((new PNGMetadata($this->path))->toArray());

		// ksort() order: IHDR, bKGD, exif, sRGB (uppercase sorts before lowercase).
		self::assertSame(['IHDR', 'bKGD', 'exif', 'sRGB'], $keys);
	}

	#[Test]
	public function to_string_contains_v1_column_layout(): void
	{
		$output = (string) new PNGMetadata($this->path);

		self::assertStringContainsString('--Metadata--', $output);
		self::assertStringContainsString('--Value--', $output);
		self::assertStringContainsString('IHDR:ImageWidth', $output);
		self::assertStringContainsString('exif:Make', $output);
		self::assertStringContainsString('SONY', $output);
	}
}
