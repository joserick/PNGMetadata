<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Exception\TruncatedDataException;
use PNGMetadata\Metadata\BackgroundColor;
use PNGMetadata\Metadata\ColorType;
use PNGMetadata\Metadata\HeaderData;
use PNGMetadata\Metadata\InterlaceMethod;
use PNGMetadata\Metadata\MetadataDocument;
use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\Metadata\TextEntry;
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
#[CoversClass(ChunkDecodingException::class)]
#[CoversClass(TruncatedDataException::class)]
#[CoversClass(InvalidPngException::class)]
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
	public function ihdr_failures_are_fatal_in_strict_mode_too(): void
	{
		$this->expectException(ChunkDecodingException::class);
		PngMetadataReader::create(strict: true)->read(
			PngBuilder::create()->withIhdr(10, 10, 16, 3)->build(),
		);
	}

	#[Test]
	public function truncated_datastream_stays_fatal_in_lenient_mode(): void
	{
		// A tEXt header claiming far more bytes than remain is a stream-level
		// failure, not a chunk decode failure: the unified policy must not
		// swallow it. Framing is built by hand because lengths must be wrong.
		$png = PngBuilder::SIGNATURE
			. PngBuilder::chunk('IHDR', \pack('NNCCCCC', 10, 10, 8, 6, 0, 0, 0))
			. \pack('N', 9999) . 'tEXt' . 'short';

		$this->expectException(TruncatedDataException::class);
		PngMetadataReader::create()->read($png);
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
	public function malformed_text_chunks_are_omitted_in_lenient_mode(): void
	{
		$png = PngBuilder::create()
			->addChunk('tEXt', 'NoTerminator')                    // missing null byte
			->addText('Title', 'OK')
			->addChunk('tEXt', \str_repeat('K', 80) . "\0text")   // oversized keyword
			->build();

		$doc = PngMetadataReader::create()->read($png);

		self::assertCount(1, $doc->texts);
		self::assertSame('Title', $doc->texts[0]->keyword);
	}

	#[Test]
	public function malformed_text_chunks_raise_in_strict_mode(): void
	{
		$png = PngBuilder::create()->addChunk('tEXt', 'NoTerminator')->build();

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('Decode failure in chunk "tEXt" at offset');

		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function malformed_itxt_is_omitted_in_lenient_mode(): void
	{
		$png = PngBuilder::create()
			->addChunk('iTXt', "Comment\0\x00") // stops right after the compression flag byte
			->addText('Title', 'still extracted')
			->build();

		$doc = PngMetadataReader::create()->read($png);

		self::assertCount(1, $doc->texts);
		self::assertSame('still extracted', $doc->texts[0]->text);
	}

	#[Test]
	public function malformed_itxt_raises_in_strict_mode(): void
	{
		$png = PngBuilder::create()->addChunk('iTXt', "Comment\0\x00")->build();

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('compression flag/method bytes');

		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function compressed_itxt_with_invalid_zlib_follows_the_policy(): void
	{
		$png = PngBuilder::create()
			->addChunk('iTXt', "Note\0\x01\x00\0\0not-zlib-data")
			->build();

		self::assertSame([], PngMetadataReader::create()->read($png)->texts);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('zlib inflation failed');

		PngMetadataReader::create(strict: true)->read($png);
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

	#[Test]
	public function valid_exif_profile_is_decoded(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addExif(PngBuilder::exifProfile())->build(),
		);

		self::assertSame('SONY', $doc->exif['IFD0']['Make'] ?? null);
	}

	#[Test]
	public function corrupt_exif_follows_the_policy(): void
	{
		$png = PngBuilder::create()
			->addExif('not-an-exif-profile')
			->addText('Title', 'OK')
			->build();

		$doc = PngMetadataReader::create()->read($png);
		self::assertNull($doc->exif);
		self::assertSame('OK', $doc->texts[0]->text);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('invalid TIFF header');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function late_exif_is_decoded_lenient_and_rejected_strict(): void
	{
		$png = PngBuilder::SIGNATURE
			. PngBuilder::chunk('IHDR', \pack('NNCCCCC', 10, 10, 8, 6, 0, 0, 0))
			. PngBuilder::chunk('IDAT', \str_repeat("\x00", 8))
			. PngBuilder::chunk('eXIf', PngBuilder::exifProfile())
			. PngBuilder::chunk('IEND', '');

		$doc = PngMetadataReader::create()->read($png);
		self::assertSame('SONY', $doc->exif['IFD0']['Make'] ?? null);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('eXIf must appear before the first IDAT');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function malformed_xmp_raises_in_strict_mode(): void
	{
		$png = PngBuilder::create()->addXmp('<broken')->build();

		self::assertNull(PngMetadataReader::create()->read($png)->xmp);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('XMP packet is not well-formed XML');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function empty_but_valid_xmp_packet_is_not_a_violation(): void
	{
		$xml = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
			. '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
			. '<rdf:Description/>'
			. '</rdf:RDF></x:xmpmeta>';

		$doc = PngMetadataReader::create(strict: true)->read(
			PngBuilder::create()->addXmp($xml)->build(),
		);

		self::assertNull($doc->xmp);
	}

	#[Test]
	public function duplicate_xmp_packets_keep_the_first_in_lenient_mode(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addXmp('<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
					. '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>first</dc:title></rdf:Description>'
					. '</rdf:RDF></x:xmpmeta>')
				->addXmp('<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
					. '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>second</dc:title></rdf:Description>'
					. '</rdf:RDF></x:xmpmeta>')
				->build(),
		);

		self::assertSame('first', $doc->xmp['dc']['title'] ?? null);
	}

	#[Test]
	public function unknown_srgb_intent_follows_the_policy(): void
	{
		$png = PngBuilder::create()->addSrgb(9)->build();

		$doc = PngMetadataReader::create()->read($png);
		self::assertNull($doc->renderingIntent);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('unknown sRGB rendering intent byte 9');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function empty_srgb_chunk_is_a_decode_failure(): void
	{
		$png = PngBuilder::create()->addChunk('sRGB', '')->build();

		self::assertNull(PngMetadataReader::create()->read($png)->renderingIntent);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('sRGB chunk has no data');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function duplicate_srgb_is_first_wins_lenient_and_an_error_strict(): void
	{
		$png = PngBuilder::create()->addSrgb(0)->addSrgb(2)->build();

		$doc = PngMetadataReader::create()->read($png);
		self::assertSame(RenderingIntent::Perceptual, $doc->renderingIntent);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('only one sRGB chunk is allowed');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function short_bkgd_payload_follows_the_policy(): void
	{
		$png = PngBuilder::create()->withIhdr(10, 10, 8, 2)->addBkgd("\x01")->build();

		self::assertNull(PngMetadataReader::create()->read($png)->background);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('bKGD needs at least 6 byte');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function unknown_ancillary_chunks_are_never_an_error_in_either_mode(): void
	{
		$png = PngBuilder::create()
			->addChunk('vpAg', "\x00\x00\x00\x00\x03\x00\x00\x00\x00\x02\x00")
			->addSrgb(0)
			->build();

		self::assertSame(RenderingIntent::Perceptual, PngMetadataReader::create()->read($png)->renderingIntent);
		self::assertSame(RenderingIntent::Perceptual, PngMetadataReader::create(strict: true)->read($png)->renderingIntent);
	}

	#[Test]
	public function bkgd_without_header_raises_a_domain_failure_not_a_logic_crash(): void
	{
		$payload = "\x00\x01\x00\x02\x00\x03";
		$stream = \fopen('php://temp', 'r+b');
		self::assertIsResource($stream);
		\fwrite($stream, $payload);
		\rewind($stream);

		$chunk = new RawChunk(ChunkType::fromBytes('bKGD'), \strlen($payload), 0, 0, $stream);

		try {
			$this->expectException(ChunkDecodingException::class);
			$this->expectExceptionMessage('bKGD cannot be interpreted before the IHDR chunk');
			(new BkgdDecoder())->decode($chunk, new DecodingContext(), new MetadataBuilder());
		} finally {
			\fclose($stream);
		}
	}
}
