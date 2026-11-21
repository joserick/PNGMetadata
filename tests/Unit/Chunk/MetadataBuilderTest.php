<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Chunk;

use PNGMetadata\Chunk\MetadataBuilder;
use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\BackgroundColor;
use PNGMetadata\Metadata\Chromaticities;
use PNGMetadata\Metadata\ColorSource;
use PNGMetadata\Metadata\Gamma;
use PNGMetadata\Metadata\IccProfile;
use PNGMetadata\Metadata\MetadataDocument;
use PNGMetadata\Metadata\RenderingIntent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetadataBuilder::class)]
#[CoversClass(MetadataDocument::class)]
#[CoversClass(BackgroundColor::class)]
#[CoversClass(RenderingIntent::class)]
#[CoversClass(ChunkDecodingException::class)]
#[CoversClass(Gamma::class)]
#[CoversClass(Chromaticities::class)]
#[CoversClass(IccProfile::class)]
#[CoversClass(ColorSource::class)]
final class MetadataBuilderTest extends TestCase
{
	#[Test]
	public function singleton_chunks_keep_the_first_instance_when_lenient(): void
	{
		$builder = new MetadataBuilder();

		$builder->setRenderingIntent(RenderingIntent::Perceptual, 'sRGB', 8);
		$builder->setRenderingIntent(RenderingIntent::Saturation, 'sRGB', 40);
		$builder->setBackground(BackgroundColor::rgb(1, 2, 3), 'bKGD', 60);
		$builder->setBackground(BackgroundColor::rgb(9, 9, 9), 'bKGD', 80);
		$builder->setExif(['Make' => 'SONY'], 'raw-first', 'eXIf', 90);
		$builder->setExif(['Make' => 'CANON'], 'raw-second', 'eXIf', 120);
		$builder->setXmp(['dc' => ['title' => 'first']], 'iTXt', 130);
		$builder->setXmp(['dc' => ['title' => 'second']], 'iTXt', 160);

		$document = $builder->build();

		self::assertSame(RenderingIntent::Perceptual, $document->renderingIntent);
		self::assertSame('1 2 3', $document->background?->toString());
		self::assertSame('SONY', $document->exif['Make'] ?? null);
		self::assertSame('first', $document->xmp['dc']['title'] ?? null);
	}

	#[Test]
	public function duplicate_singletons_raise_a_decode_failure_when_strict(): void
	{
		$builder = new MetadataBuilder(strict: true);
		$builder->setRenderingIntent(RenderingIntent::Perceptual, 'sRGB', 8);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('Decode failure in chunk "sRGB" at offset 40: only one sRGB chunk is allowed');

		$builder->setRenderingIntent(RenderingIntent::Saturation, 'sRGB', 40);
	}

	#[Test]
	public function color_source_precedence_is_resolved_at_build_time(): void
	{
		$builder = new MetadataBuilder();
		$builder->setGamma(new Gamma(45455), 'gAMA', 8);
		$builder->setChromaticities(self::chromaticities(), 'cHRM', 20);
		$builder->setRenderingIntent(RenderingIntent::Perceptual, 'sRGB', 52);
		$builder->setIccProfile(self::iccProfile(), 'iCCP', 64);

		self::assertSame(ColorSource::Iccp, $builder->build()->colorSource);
	}

	#[Test]
	public function color_source_falls_back_to_chromaticities_without_higher_priority_chunks(): void
	{
		$builder = new MetadataBuilder();
		$builder->setGamma(new Gamma(45455), 'gAMA', 8);
		$builder->setChromaticities(self::chromaticities(), 'cHRM', 20);

		self::assertSame(ColorSource::Chromaticities, $builder->build()->colorSource);
	}

	#[Test]
	public function gamma_alone_does_not_produce_a_color_source(): void
	{
		$builder = new MetadataBuilder();
		$builder->setGamma(new Gamma(45455), 'gAMA', 8);

		self::assertNull($builder->build()->colorSource);
	}

	private static function chromaticities(): Chromaticities
	{
		return Chromaticities::fromScaled(31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000);
	}

	private static function iccProfile(): IccProfile
	{
		return new IccProfile(
			name: 'ICC Profile',
			description: 'sRGB IEC61966-2.1',
			version: '4.3.0',
			profileClass: 'mntr',
			colorSpace: 'RGB',
			pcs: 'XYZ',
			renderingIntent: RenderingIntent::Perceptual,
		);
	}
}
