<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Metadata;

use PNGMetadata\Chunk\BkgdDecoder;
use PNGMetadata\Chunk\ChunkDecoderRegistry;
use PNGMetadata\Chunk\DecodingContext;
use PNGMetadata\Chunk\ExifDecoder;
use PNGMetadata\Chunk\IhdrDecoder;
use PNGMetadata\Chunk\ItxtDecoder;
use PNGMetadata\Chunk\MetadataBuilder;
use PNGMetadata\Chunk\SrgbDecoder;
use PNGMetadata\Chunk\TextDecoder;
use PNGMetadata\Chunk\XmpDecoder;
use PNGMetadata\Exception\InvalidPngException;
use PNGMetadata\Metadata\BackgroundColor;
use PNGMetadata\Metadata\ColorType;
use PNGMetadata\Metadata\HeaderData;
use PNGMetadata\Metadata\InterlaceMethod;
use PNGMetadata\Metadata\MetadataDocument;
use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\Metadata\TextEntry;
use PNGMetadata\Parsing\BinaryStream;
use PNGMetadata\Parsing\ChunkType;
use PNGMetadata\Parsing\PngReader;
use PNGMetadata\Parsing\RawChunk;
use PNGMetadata\PngMetadataReader;
use PNGMetadata\Tests\Fixtures\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PngMetadataReader::class)]
#[CoversClass(PngReader::class)]
#[CoversClass(RawChunk::class)]
#[CoversClass(ChunkType::class)]
#[CoversClass(BinaryStream::class)]
#[CoversClass(ChunkDecoderRegistry::class)]
#[CoversClass(DecodingContext::class)]
#[CoversClass(MetadataBuilder::class)]
#[CoversClass(IhdrDecoder::class)]
#[CoversClass(TextDecoder::class)]
#[CoversClass(ItxtDecoder::class)]
#[CoversClass(ExifDecoder::class)]
#[CoversClass(SrgbDecoder::class)]
#[CoversClass(BkgdDecoder::class)]
#[CoversClass(XmpDecoder::class)]
#[CoversClass(MetadataDocument::class)]
#[CoversClass(HeaderData::class)]
#[CoversClass(ColorType::class)]
#[CoversClass(InterlaceMethod::class)]
#[CoversClass(RenderingIntent::class)]
#[CoversClass(TextEntry::class)]
#[CoversClass(BackgroundColor::class)]
#[CoversClass(InvalidPngException::class)]
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
	public function it_rejects_non_png_input_at_reader_level(): void
	{
		$this->expectException(InvalidPngException::class);
		PngMetadataReader::create()->read('GIF89a-not-a-png');
	}

	#[Test]
	public function it_preserves_repeated_text_entries_in_file_order(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addText('Comment', 'first')
				->addText('Comment', 'second')
				->build(),
		);

		self::assertCount(2, $doc->texts);
		self::assertSame('first', $doc->texts[0]->text);
		self::assertSame('second', $doc->texts[1]->text);
	}

	#[Test]
	public function it_exposes_thumbnail_bytes_without_gd(): void
	{
		// A PNG without EXIF has no thumbnail to expose.
		$doc = PngMetadataReader::create()->read(PngBuilder::create()->build());

		self::assertNull($doc->thumbnailBytes());
	}

	#[Test]
	public function it_exposes_the_embedded_exif_thumbnail_bytes(): void
	{
		$thumbnail = "\xFF\xD8\xFF\xE0THUMB\xFF\xD9";

		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addExif(PngBuilder::exifProfile(thumbnail: $thumbnail))->build(),
		);

		self::assertSame($thumbnail, $doc->thumbnailBytes());
	}

	#[Test]
	public function it_serializes_the_full_text_and_section_contract(): void
	{
		$xml = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
			. '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
			. '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
			. '<dc:title>Mi Título</dc:title>'
			. '</rdf:Description></rdf:RDF></x:xmpmeta>';

		$array = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addSrgb(0)
				->addBkgd(\pack('nnn', 1, 2, 3))
				->addText('Title', 'Example')
				->addItxt('Note', 'Texto', 'es', 'Nota', compressed: true)
				->addXmp($xml)
				->addExif(PngBuilder::exifProfile())
				->build(),
		)->toArray();

		self::assertSame(800, $array['IHDR']['ImageWidth']);
		self::assertSame('Perceptual', $array['sRGB']);
		self::assertSame('1 2 3', $array['bKGD']);
		self::assertSame('SONY', $array['exif']['IFD0']['Make'] ?? null);
		self::assertSame('Mi Título', $array['xmp']['dc']['title'] ?? null);
		self::assertSame([
			['keyword' => 'Title', 'text' => 'Example', 'chunk' => 'tEXt'],
			[
				'keyword' => 'Note',
				'text' => 'Texto',
				'language' => 'es',
				'translatedKeyword' => 'Nota',
				'compressed' => true,
				'chunk' => 'iTXt',
			],
		], $array['texts']);
	}

	#[Test]
	public function an_explicit_empty_registry_disables_all_chunk_decoding(): void
	{
		$doc = (new PngMetadataReader(new ChunkDecoderRegistry([])))
			->read(PngBuilder::create()->build());

		self::assertNull($doc->header);
	}

	#[Test]
	public function the_registry_can_be_replaced_with_a_custom_decoder_set(): void
	{
		$doc = (new PngMetadataReader(new ChunkDecoderRegistry([new SrgbDecoder()])))
			->read(PngBuilder::create()->addSrgb(2)->build());

		self::assertNull($doc->header);
		self::assertSame(RenderingIntent::Saturation, $doc->renderingIntent);
	}

	#[Test]
	public function default_constructor_is_lenient(): void
	{
		$doc = (new PngMetadataReader())->read(
			PngBuilder::create()->addSrgb(0)->addSrgb(2)->build(),
		);

		self::assertSame(RenderingIntent::Perceptual, $doc->renderingIntent);
	}
}
