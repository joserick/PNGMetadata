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
use PNGMetadata\Chunk\ChunkDecoderInterface;
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
use PHPUnit\Framework\Attributes\DataProvider;
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

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function malformedIhdrCases(): iterable
	{
		yield 'unknown color type 5' => [
			PngBuilder::create()->withIhdr(10, 10, 8, 5)->build(),
			'Unknown IHDR color type 5',
		];
		yield 'unknown interlace method 2' => [
			PngBuilder::create()->withIhdr(10, 10, 8, 0, 0, 0, 2)->build(),
			'Unknown IHDR interlace method 2',
		];
		yield 'payload shorter than 13 bytes' => [
			PngBuilder::SIGNATURE
				. PngBuilder::chunk('IHDR', \pack('NNCCCC', 10, 10, 8, 0, 0, 0))
				. PngBuilder::chunk('IEND', ''),
			'IHDR must be 13 bytes',
		];
	}

	/**
	 * IHDR is the interpretation context for later chunks: its failure is fatal
	 * in both modes (adaptation of spec section 11.2.1).
	 *
	 * @param string $png
	 * @param string $message
	 */
	#[Test]
	#[DataProvider('malformedIhdrCases')]
	public function malformed_ihdr_is_fatal_in_both_modes(string $png, string $message): void
	{
		foreach ([false, true] as $strict) {
			try {
				PngMetadataReader::create(strict: $strict)->read($png);
				self::fail('Malformed IHDR must abort the read in strict=' . ($strict ? 'true' : 'false') . '.');
			} catch (ChunkDecodingException $e) {
				self::assertStringContainsString($message, $e->getMessage());
			}
		}
	}

	#[Test]
	public function ihdr_failures_are_fatal_in_strict_mode_too(): void
	{
		$this->expectException(ChunkDecodingException::class);
		PngMetadataReader::create(strict: true)->read(
			PngBuilder::create()->withIhdr(10, 10, 16, 3)->build(),
		);
	}

	/**
	 * The IHDR-fatal rule guards the header-establishing chunk (the first one)
	 * only; duplicates elsewhere in the stream are policy-governed decode
	 * failures (PNG spec §11.2.1: IHDR occurs exactly once, at the start).
	 */
	#[Test]
	public function late_ihdr_does_not_overwrite_the_header_in_lenient_mode(): void
	{
		$png = PngBuilder::create()
			->withIhdr(10, 10, 8, 2)
			->addText('Title', 'kept')
			->addIdat(64)
			->addChunk('IHDR', \pack('NNCCCCC', 640, 480, 8, 2, 0, 0, 0))
			->build();

		$doc = PngMetadataReader::create()->read($png);

		self::assertSame(10, $doc->header->width);
		self::assertSame(10, $doc->header->height);
		self::assertSame('kept', $doc->texts[0]->text);
	}

	#[Test]
	public function malformed_late_ihdr_does_not_abort_lenient_extraction(): void
	{
		$png = PngBuilder::create()
			->withIhdr(10, 10, 8, 2)
			->addText('Title', 'kept')
			->addIdat(64)
			->addChunk('IHDR', 'too short')
			->build();

		$doc = PngMetadataReader::create()->read($png);

		self::assertSame(10, $doc->header->width);
		self::assertCount(1, $doc->texts);
	}

	#[Test]
	public function late_ihdr_is_a_conformance_failure_in_strict_mode(): void
	{
		$png = PngBuilder::create()
			->withIhdr(10, 10, 8, 2)
			->addIdat(64)
			->addChunk('IHDR', \pack('NNCCCCC', 640, 480, 8, 2, 0, 0, 0))
			->build();

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('only one IHDR chunk');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function empty_first_xmp_packet_still_wins_the_singleton_slot(): void
	{
		$empty = '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>';
		$filled = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
			. '<rdf:RDF xmlns:rdf="http://www.w3.org/1999-02-22-rdf-syntax-ns#">'
			. '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>second</dc:title></rdf:Description>'
			. '</rdf:RDF></x:xmpmeta>';
		$png = PngBuilder::create()->addXmp($empty)->addXmp($filled)->build();

		// Lenient: the first (well-formed) packet claims the slot even with no
		// extractable properties; the later one is ignored.
		self::assertNull(PngMetadataReader::create()->read($png)->xmp);

		// Strict: the duplicate is reported, regardless of the first being empty.
		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('only one XMP packet chunk is allowed');
		PngMetadataReader::create(strict: true)->read($png);
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

	/**
	 * Keyword length boundaries for both text chunk types: 79 bytes is the
	 * spec maximum, 80 is malformed (spec sections 11.3.3.2 and 11.3.3.4).
	 *
	 * @return iterable<string, array{string, int, bool}>
	 */
	public static function keywordBoundaryCases(): iterable
	{
		foreach (['tEXt', 'iTXt'] as $type) {
			foreach ([79 => true, 80 => false] as $length => $valid) {
				yield \sprintf('%s keyword of %d bytes', $type, $length) => [$type, $length, $valid];
			}
		}
	}

	#[Test]
	#[DataProvider('keywordBoundaryCases')]
	public function keyword_length_boundary_is_enforced(string $type, int $length, bool $valid): void
	{
		$keyword = \str_repeat('K', $length);
		$png = PngBuilder::create()
			->addChunk($type, $keyword . "\0" . ($type === 'iTXt' ? "\x00\x00\0\0" : '') . 'value')
			->build();

		if ($valid) {
			$doc = PngMetadataReader::create()->read($png);

			self::assertCount(1, $doc->texts);
			self::assertSame($keyword, $doc->texts[0]->keyword);

			return;
		}

		self::assertSame([], PngMetadataReader::create()->read($png)->texts);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('keyword must be 1-79 bytes');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function empty_itxt_keyword_follows_the_policy(): void
	{
		$png = PngBuilder::create()->addChunk('iTXt', "\0\x00\x00\0\0value")->build();

		self::assertSame([], PngMetadataReader::create()->read($png)->texts);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('iTXt keyword must be 1-79 bytes');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function itxt_ending_right_after_the_flag_bytes_follows_the_policy(): void
	{
		// keyword + null + flag + method = 4 bytes: the payload ends exactly
		// where the language-tag separator must begin (offset + 2 == length).
		$png = PngBuilder::create()->addChunk('iTXt', "k\0\x00\x00")->build();

		self::assertSame([], PngMetadataReader::create()->read($png)->texts);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('missing the null separator after the language tag');
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
		// The XMP-carrying iTXt is a metadata carrier, not a text entry.
		self::assertSame([], $doc->texts);
	}

	#[Test]
	public function xmp_extraction_handles_namespaces_nesting_and_whitespace(): void
	{
		$xml = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
			. '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
			. '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/" '
			. 'xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/">'
			. '<dc:title>  Mi Título  </dc:title>'
			. '<dc:creator><rdf:Seq><rdf:li>Ana</rdf:li></rdf:Seq></dc:creator>'
			. '<dc:subject><rdf:Bag/></dc:subject>'
			. '<dc:format/>'
			. '<photoshop:Headline>  Head  </photoshop:Headline>'
			. '</rdf:Description></rdf:RDF></x:xmpmeta>';

		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addXmp($xml)->build(),
		);

		self::assertSame([
			'dc' => [
				'title' => 'Mi Título',      // surrounding whitespace trimmed
				'creator' => 'Ana',          // nested container text folded
				'format' => '',              // empty leaf preserved
			],
			'photoshop' => [
				'Headline' => 'Head',
			],
		], $doc->xmp);
	}

	#[Test]
	public function whitespace_only_xmp_packet_is_not_a_violation(): void
	{
		$png = PngBuilder::create()->addXmp("  \n\t ")->build();

		self::assertNull(PngMetadataReader::create()->read($png)->xmp);
		self::assertNull(PngMetadataReader::create(strict: true)->read($png)->xmp);
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

	/**
	 * bKGD payload shape depends on every IHDR color type (spec section
	 * 11.3.4.1); each row must decode its own variant, including the
	 * with-alpha types 4 and 6.
	 *
	 * @return iterable<string, array{int, string, string}>
	 */
	public static function bkgdCases(): iterable
	{
		yield 'grayscale' => [0, "\x00\x80", '128'];
		yield 'rgb' => [2, \pack('nnn', 65535, 0, 32768), '65535 0 32768'];
		yield 'palette index' => [3, \chr(7), '7'];
		yield 'grayscale with alpha' => [4, "\x01\x00", '256'];
		yield 'rgb with alpha' => [6, \pack('nnn', 1, 2, 3), '1 2 3'];
	}

	#[Test]
	#[DataProvider('bkgdCases')]
	public function it_decodes_bkgd_by_color_type(int $colorType, string $payload, string $expected): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->withIhdr(10, 10, 8, $colorType)->addBkgd($payload)->build(),
		);

		self::assertSame($expected, $doc->background?->toString());
	}

	/**
	 * One byte short of the required payload for every color type.
	 *
	 * @return iterable<string, array{int, string, int}>
	 */
	public static function shortBkgdCases(): iterable
	{
		yield 'grayscale needs 2' => [0, "\x80", 2];
		yield 'rgb needs 6' => [2, "\x01", 6];
		yield 'palette needs 1' => [3, '', 1];
		yield 'grayscale with alpha needs 2' => [4, "\x01", 2];
		yield 'rgb with alpha needs 6' => [6, "\x00\x01\x00\x02\x00", 6];
	}

	#[Test]
	#[DataProvider('shortBkgdCases')]
	public function short_bkgd_payload_follows_the_policy(int $colorType, string $payload, int $required): void
	{
		$png = PngBuilder::create()->withIhdr(10, 10, 8, $colorType)->addBkgd($payload)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->background);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage(\sprintf('bKGD needs at least %d byte', $required));
		PngMetadataReader::create(strict: true)->read($png);
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
	public function tiff_magic_helper_recognizes_both_byte_orders(): void
	{
		self::assertTrue(ExifDecoder::hasValidMagic("II\x2A\x00"));
		self::assertTrue(ExifDecoder::hasValidMagic("MM\x00\x2A"));
		self::assertFalse(ExifDecoder::hasValidMagic("II*X"));
	}

	#[Test]
	public function the_default_registry_covers_every_supported_chunk(): void
	{
		self::assertSame(
			['IHDR', 'tEXt', 'iTXt', 'eXIf', 'sRGB', 'bKGD'],
			\array_map(
				static fn (ChunkDecoderInterface $decoder): string => $decoder->supports(),
				ChunkDecoderRegistry::defaults(),
			),
		);
	}

	#[Test]
	public function decoding_context_defaults_to_lenient_without_a_header(): void
	{
		$context = new DecodingContext();

		self::assertFalse($context->strict);
		self::assertNull($context->header);
	}

	#[Test]
	public function big_endian_exif_profile_is_decoded(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addExif(PngBuilder::exifProfile('NIKON', bigEndian: true))->build(),
		);

		self::assertSame('NIKON', $doc->exif['IFD0']['Make'] ?? null);
	}

	/**
	 * The TIFF magic is exactly four bytes ("II*\0" / "MM\0*", spec
	 * §11.3.4.5.2); a near-miss must not be accepted as the other endianness.
	 *
	 * @return iterable<string, array{string}>
	 */
	public static function invalidTiffMagicProfiles(): iterable
	{
		yield 'little endian with wrong fourth byte' => ["II*X"];
		yield 'big endian with wrong fourth byte' => ["MM\0X"];
	}

	#[Test]
	#[DataProvider('invalidTiffMagicProfiles')]
	public function truncated_tiff_magic_is_rejected(string $payload): void
	{
		$png = PngBuilder::create()->addExif($payload)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->exif);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('invalid TIFF header');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function exif_with_valid_magic_but_unreadable_payload_follows_the_policy(): void
	{
		$png = PngBuilder::create()->addExif("II\x2A\x00\x00\x00")->build();

		self::assertNull(PngMetadataReader::create()->read($png)->exif);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('the Exif profile could not be decoded');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function exif_after_fdat_is_rejected_in_strict_mode(): void
	{
		$png = PngBuilder::SIGNATURE
			. PngBuilder::chunk('IHDR', \pack('NNCCCCC', 10, 10, 8, 6, 0, 0, 0))
			. PngBuilder::chunk('fdAT', "\x00\x00\x00\x01" . \str_repeat("\x00", 8))
			. PngBuilder::chunk('eXIf', PngBuilder::exifProfile())
			. PngBuilder::chunk('IEND', '');

		$doc = PngMetadataReader::create()->read($png);
		self::assertSame('SONY', $doc->exif['IFD0']['Make'] ?? null);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('eXIf must appear before the first IDAT');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function exif_after_a_non_image_chunk_is_allowed_in_strict_mode(): void
	{
		$png = PngBuilder::SIGNATURE
			. PngBuilder::chunk('IHDR', \pack('NNCCCCC', 10, 10, 8, 6, 0, 0, 0))
			. PngBuilder::chunk('sRGB', \chr(0))
			. PngBuilder::chunk('eXIf', PngBuilder::exifProfile())
			. PngBuilder::chunk('IEND', '');

		$doc = PngMetadataReader::create(strict: true)->read($png);

		self::assertSame(RenderingIntent::Perceptual, $doc->renderingIntent);
		self::assertSame('SONY', $doc->exif['IFD0']['Make'] ?? null);
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
