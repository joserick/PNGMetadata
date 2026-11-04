<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Chunk;

use PNGMetadata\Chunk\MetadataBuilder;
use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\BackgroundColor;
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
}
