<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\ColorType;
use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\PngMetadataReader;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PngMetadataReader::class)]
final class ChunkDecodersTest extends TestCase
{
	#[Test]
	public function it_decodes_ihdr_with_enums(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->withIhdr(1024, 768, 8, 6, 0, 0, 1)->build(),
		);

		self::assertSame(1024, $doc->header?->width);
		self::assertSame(768, $doc->header?->height);
		self::assertSame(ColorType::RGBWithAlpha, $doc->header?->colorType);
		self::assertSame('Adam7 Interlace', $doc->header?->interlaceMethod->label());
	}

	#[Test]
	public function it_rejects_invalid_bit_depth_for_color_type(): void
	{
		$this->expectException(ChunkDecodingException::class);
		PngMetadataReader::create()->read(
			PngBuilder::create()->withIhdr(10, 10, 16, 3)->build(),
		);
	}

	#[Test]
	public function it_converts_text_from_latin1_to_utf8(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addText('Title', "Caf\xe9 PNG")->build(),
		);

		self::assertSame('Café PNG', $doc->texts[0]->text);
	}

	#[Test]
	public function it_decodes_itxt_positionally(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addItxt('Comment', 'Hola mundo', 'es', 'Comentario')->build(),
		);

		$entry = $doc->texts[0];
		self::assertSame('Comment', $entry->keyword);
		self::assertSame('Hola mundo', $entry->text);
		self::assertSame('es', $entry->languageTag);
		self::assertSame('Comentario', $entry->translatedKeyword);
	}

	#[Test]
	public function it_inflates_compressed_itxt(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addItxt('Note', 'Texto secreto', compressed: true)->build(),
		);

		self::assertSame('Texto secreto', $doc->texts[0]->text);
		self::assertTrue($doc->texts[0]->compressed);
	}

	#[Test]
	public function it_parses_xmp_from_itxt(): void
	{
		$xml = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
			. '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
			. '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
			. '<dc:title>Mi Título</dc:title>'
			. '</rdf:Description></rdf:RDF></x:xmpmeta>';

		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addXmp($xml)->build(),
		);

		self::assertSame('Mi Título', $doc->xmp['dc']['title'] ?? null);
	}

	#[Test]
	public function it_omits_invalid_xmp_without_aborting(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addXmp('<broken')->addText('Title', 'OK')->build(),
		);

		self::assertNull($doc->xmp);
		self::assertSame('OK', $doc->texts[0]->text);
	}

	#[Test]
	public function it_decodes_srgb_intent(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addSrgb(0)->build(),
		);

		self::assertSame(RenderingIntent::Perceptual, $doc->renderingIntent);
	}

	#[Test]
	public function it_decodes_bkgd_by_color_type(): void
	{
		$rgb = PngMetadataReader::create()->read(
			PngBuilder::create()->withIhdr(10, 10, 8, 2)->addBkgd(\pack('nnn', 65535, 0, 32768))->build(),
		);
		self::assertSame('65535 0 32768', $rgb->background?->toString());

		$gray = PngMetadataReader::create()->read(
			PngBuilder::create()->withIhdr(10, 10, 8, 0)->addBkgd(\pack('n', 128))->build(),
		);
		self::assertSame('128', $gray->background?->toString());

		$palette = PngMetadataReader::create()->read(
			PngBuilder::create()->withIhdr(10, 10, 8, 3)->addBkgd(\chr(7))->build(),
		);
		self::assertSame('7', $palette->background?->toString());
	}
}
