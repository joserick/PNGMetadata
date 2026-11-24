<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Exception\TruncatedDataException;
use PNGMetadata\Metadata\BackgroundColor;
use PNGMetadata\Metadata\Chromaticities;
use PNGMetadata\Metadata\ColorSource;
use PNGMetadata\Metadata\ColorType;
use PNGMetadata\Metadata\Gamma;
use PNGMetadata\Metadata\HeaderData;
use PNGMetadata\Metadata\IccProfile;
use PNGMetadata\Metadata\InterlaceMethod;
use PNGMetadata\Metadata\MetadataDocument;
use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\Metadata\TextEntry;
use PNGMetadata\Metadata\Transparency;
use PNGMetadata\Chunk\BkgdDecoder;
use PNGMetadata\Chunk\ChrmDecoder;
use PNGMetadata\Chunk\ChunkDecoderInterface;
use PNGMetadata\Chunk\ChunkDecoderRegistry;
use PNGMetadata\Chunk\DecodingContext;
use PNGMetadata\Chunk\ExifDecoder;
use PNGMetadata\Chunk\GamaDecoder;
use PNGMetadata\Chunk\IccpDecoder;
use PNGMetadata\Chunk\IhdrDecoder;
use PNGMetadata\Chunk\ItxtDecoder;
use PNGMetadata\Chunk\MetadataBuilder;
use PNGMetadata\Chunk\SrgbDecoder;
use PNGMetadata\Chunk\TextDecoder;
use PNGMetadata\Chunk\TrnsDecoder;
use PNGMetadata\Chunk\XmpDecoder;
use PNGMetadata\Chunk\ZtxtDecoder;
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
#[CoversClass(ZtxtDecoder::class)]
#[CoversClass(ItxtDecoder::class)]
#[CoversClass(ExifDecoder::class)]
#[CoversClass(SrgbDecoder::class)]
#[CoversClass(IccpDecoder::class)]
#[CoversClass(GamaDecoder::class)]
#[CoversClass(ChrmDecoder::class)]
#[CoversClass(BkgdDecoder::class)]
#[CoversClass(TrnsDecoder::class)]
#[CoversClass(XmpDecoder::class)]
#[CoversClass(MetadataDocument::class)]
#[CoversClass(HeaderData::class)]
#[CoversClass(ColorType::class)]
#[CoversClass(InterlaceMethod::class)]
#[CoversClass(RenderingIntent::class)]
#[CoversClass(TextEntry::class)]
#[CoversClass(BackgroundColor::class)]
#[CoversClass(Gamma::class)]
#[CoversClass(Chromaticities::class)]
#[CoversClass(IccProfile::class)]
#[CoversClass(ColorSource::class)]
#[CoversClass(Transparency::class)]
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
		yield 'zero width' => [
			PngBuilder::create()->withIhdr(0, 10, 8, 2)->build(),
			'width must be greater than 0',
		];
		yield 'zero height' => [
			PngBuilder::create()->withIhdr(10, 0, 8, 2)->build(),
			'height must be greater than 0',
		];
		yield 'compression method 1' => [
			PngBuilder::create()->withIhdr(10, 10, 8, 2, 1)->build(),
			'compression method must be 0',
		];
		yield 'filter method 1' => [
			PngBuilder::create()->withIhdr(10, 10, 8, 2, 0, 1)->build(),
			'filter method must be 0',
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
	public function unknown_critical_chunk_follows_the_policy(): void
	{
		// Session fixture a04: "ZsTr" is critical (uppercase first letter) and
		// outside the recognized set; PNG spec §5.4 makes it a conformance error.
		$png = PngBuilder::create()->addChunk('ZsTr', 'x')->build();

		$doc = PngMetadataReader::create()->read($png);
		self::assertSame(800, $doc->header->width);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('unknown critical chunk');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function unknown_ancillary_chunk_never_errors_in_either_mode(): void
	{
		$png = PngBuilder::create()->addChunk('vpAg', 'x')->build();

		self::assertSame(800, PngMetadataReader::create()->read($png)->header->width);
		self::assertSame(800, PngMetadataReader::create(strict: true)->read($png)->header->width);
	}

	#[Test]
	public function oversized_bkgd_follows_the_policy(): void
	{
		// Session fixtures c06/h05: 4 bytes for Grayscale, which needs exactly 2
		// (spec §11.3.4.1). Lenient keeps its trailing-byte tolerance.
		$png = PngBuilder::create()->withIhdr(10, 10, 8, 0)->addBkgd("\x00\x05\x00\x99")->build();

		$doc = PngMetadataReader::create()->read($png);
		self::assertSame('5', $doc->background?->toString());

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('bKGD payload must be exactly 2');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function itxt_compression_fields_follow_the_policy(): void
	{
		// Session fixture e01: flag 2 is outside the defined domain (0..1).
		$flagged = PngBuilder::create()->addChunk('iTXt', "Note\0\x02\x00\0\0plain text")->build();
		self::assertSame('plain text', PngMetadataReader::create()->read($flagged)->texts[0]->text);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('compression fields are out of domain');
		PngMetadataReader::create(strict: true)->read($flagged);

		// flag 1 with a non-zero method: lenient ignores the method as today...
		$methodOne = PngBuilder::create()
			->addChunk('iTXt', "Note\0\x01\x01\0\0" . \gzcompress('hello'))
			->build();
		self::assertSame('hello', PngMetadataReader::create()->read($methodOne)->texts[0]->text);

		// ...while strict rejects the undefined method (spec §11.3.3.4).
		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('compression fields are out of domain');
		PngMetadataReader::create(strict: true)->read($methodOne);
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
	public function it_decodes_ztxt_with_chunk_metadata(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addZtxt('Title', 'Sunset')->build(),
		);

		$entry = $doc->texts[0];
		self::assertSame('Title', $entry->keyword);
		self::assertSame('Sunset', $entry->text);
		self::assertTrue($entry->compressed);
		self::assertSame('zTXt', $entry->chunkType);

		$array = $doc->toArray();
		self::assertSame('zTXt', $array['texts'][0]['chunk'] ?? null);
		self::assertTrue($array['texts'][0]['compressed'] ?? null);
	}

	#[Test]
	public function it_converts_ztxt_keyword_and_text_from_latin1_to_utf8(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addZtxt("R\xe9sum\xe9", "Caf\xe9 PNG")->build(),
		);

		self::assertSame('Résumé', $doc->texts[0]->keyword);
		self::assertSame('Café PNG', $doc->texts[0]->text);
	}

	#[Test]
	public function ztxt_keyword_of_79_bytes_is_accepted(): void
	{
		$keyword = \str_repeat('K', 79);

		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addZtxt($keyword, 'value')->build(),
		);

		self::assertCount(1, $doc->texts);
		self::assertSame($keyword, $doc->texts[0]->keyword);
	}

	#[Test]
	public function repeated_ztxt_entries_are_preserved_in_file_order(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addZtxt('Comment', 'first')
				->addText('Title', 'middle')
				->addZtxt('Comment', 'second')
				->build(),
		);

		self::assertSame(
			[['Comment', 'first'], ['Title', 'middle'], ['Comment', 'second']],
			\array_map(
				static fn (TextEntry $entry): array => [$entry->keyword, $entry->text],
				$doc->texts,
			),
		);
		self::assertSame(
			['zTXt', 'tEXt', 'zTXt'],
			\array_map(static fn (TextEntry $entry): string => $entry->chunkType, $doc->texts),
		);
	}

	/**
	 * Every unusable zTXt shape must be omitted in lenient mode and raised in
	 * strict mode (ADR-007).
	 *
	 * @return iterable<string, array{string, string}>
	 */
	public static function malformedZtxtCases(): iterable
	{
		yield 'missing null terminator' => [
			'NoTerminator',
			'zTXt payload has no null terminator',
		];
		yield 'empty keyword' => [
			"\0\x00" . \gzcompress('value'),
			'zTXt keyword must be 1-79 bytes, 0 given',
		];
		yield 'oversized keyword' => [
			\str_repeat('K', 80) . "\0\x00" . \gzcompress('value'),
			'zTXt keyword must be 1-79 bytes, 80 given',
		];
		yield 'missing method byte' => [
			"Title\0",
			'zTXt chunk is missing the compression method byte',
		];
		yield 'unsupported method' => [
			"Title\0\x01" . \gzcompress('value'),
			'zTXt compression method must be 0 (zlib), 1 given',
		];
		yield 'corrupt zlib' => [
			"Title\0\x00not-zlib-data",
			'zlib inflation failed or exceeded',
		];
	}

	#[Test]
	#[DataProvider('malformedZtxtCases')]
	public function malformed_ztxt_follows_the_policy(string $payload, string $message): void
	{
		$png = PngBuilder::create()->addChunk('zTXt', $payload)->build();

		self::assertSame([], PngMetadataReader::create()->read($png)->texts);

		try {
			PngMetadataReader::create(strict: true)->read($png);
			self::fail('Malformed zTXt must raise in strict mode.');
		} catch (ChunkDecodingException $e) {
			self::assertStringContainsString($message, $e->getMessage());
		}
	}

	#[Test]
	public function configured_ztxt_inflation_budget_accepts_text_at_the_boundary(): void
	{
		$png = PngBuilder::create()->addZtxt('Title', 'hello')->build();

		$doc = PngMetadataReader::create(maxInflatedBytes: 5)->read($png);

		self::assertSame('hello', $doc->texts[0]->text);
	}

	#[Test]
	public function ztxt_over_the_inflation_budget_follows_the_policy(): void
	{
		$png = PngBuilder::create()->addZtxt('Title', 'hello')->build();

		self::assertSame(
			[],
			PngMetadataReader::create(maxInflatedBytes: 4)->read($png)->texts,
		);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('zlib inflation failed or exceeded the 4-byte inflation budget');
		PngMetadataReader::create(strict: true, maxInflatedBytes: 4)->read($png);
	}

	#[Test]
	public function compressed_itxt_over_the_inflation_budget_follows_the_policy(): void
	{
		$png = PngBuilder::create()
			->addItxt('Note', 'hello', compressed: true)
			->build();

		self::assertSame(
			[],
			PngMetadataReader::create(maxInflatedBytes: 4)->read($png)->texts,
		);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('zlib inflation failed or exceeded the 4-byte inflation budget');
		PngMetadataReader::create(strict: true, maxInflatedBytes: 4)->read($png);
	}

	#[Test]
	public function the_inflation_budget_is_shared_across_text_chunks(): void
	{
		$png = PngBuilder::create()
			->addZtxt('First', '12345')
			->addZtxt('Second', '1234')
			->addItxt('Third', '12', compressed: true)
			->build();

		$doc = PngMetadataReader::create(maxInflatedBytes: 10)->read($png);

		self::assertCount(2, $doc->texts);
		self::assertSame('First', $doc->texts[0]->keyword);
		self::assertSame('Second', $doc->texts[1]->keyword);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('zlib inflation failed or exceeded the 10-byte inflation budget');
		PngMetadataReader::create(strict: true, maxInflatedBytes: 10)->read($png);
	}

	#[Test]
	public function a_one_byte_inflation_budget_is_valid(): void
	{
		$png = PngBuilder::create()->addZtxt('Title', 'a')->build();

		$doc = PngMetadataReader::create(maxInflatedBytes: 1)->read($png);

		self::assertSame('a', $doc->texts[0]->text);
	}

	#[Test]
	public function the_inflation_budget_resets_for_each_read(): void
	{
		$reader = PngMetadataReader::create(maxInflatedBytes: 5);
		$png = PngBuilder::create()->addZtxt('Title', 'hello')->build();

		self::assertSame('hello', $reader->read($png)->texts[0]->text);
		self::assertSame('hello', $reader->read($png)->texts[0]->text);
	}

	#[Test]
	public function a_non_positive_inflation_budget_is_rejected(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('must be greater than zero');

		PngMetadataReader::create(maxInflatedBytes: 0);
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
	public function it_parses_xmp_from_the_default_namespace(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addXmp('<xmp xmlns="urn:default"><title>Hola</title></xmp>')
				->build(),
		);

		self::assertSame('Hola', $doc->xmp['default']['title'] ?? null);
	}

	#[Test]
	public function it_keeps_default_and_prefixed_xmp_namespaces_separate(): void
	{
		$xml = '<xmp xmlns="urn:default" xmlns:dc="http://purl.org/dc/elements/1.1/">'
			. '<title>Hola</title><dc:creator>Ana</dc:creator></xmp>';

		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addXmp($xml)->build(),
		);

		self::assertSame('Hola', $doc->xmp['default']['title'] ?? null);
		self::assertSame('Ana', $doc->xmp['dc']['creator'] ?? null);
	}

	#[Test]
	public function elements_without_a_namespace_are_not_grouped_under_default(): void
	{
		$xml = '<xmp xmlns="urn:default"><title>Hola</title>'
			. '<plain xmlns="">Ignored</plain></xmp>';

		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addXmp($xml)->build(),
		);

		self::assertSame('Hola', $doc->xmp['default']['title'] ?? null);
		self::assertArrayNotHasKey('plain', $doc->xmp['default'] ?? []);
	}

	#[Test]
	public function xmp_without_any_namespace_is_not_exposed(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addXmp('<xmp><title>Hola</title></xmp>')->build(),
		);

		self::assertNull($doc->xmp);
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

	#[Test]
	public function it_decodes_gama(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addGama(45455)->build(),
		);

		self::assertSame(45455, $doc->gamma?->scaled);
		self::assertEqualsWithDelta(0.45455, $doc->gamma?->value(), 0.000001);
		self::assertEqualsWithDelta(0.45455, $doc->toArray()['gAMA'], 0.000001);
	}

	#[Test]
	public function zero_gama_follows_the_policy(): void
	{
		$png = PngBuilder::create()->addGama(0)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->gamma);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('gAMA value must be greater than zero');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function wrong_gama_length_follows_the_policy(): void
	{
		$png = PngBuilder::create()->addChunk('gAMA', "\0\0\0")->build();

		self::assertNull(PngMetadataReader::create()->read($png)->gamma);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('gAMA payload must be exactly 4 bytes');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function it_decodes_chrm(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addChrm(31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000)
				->build(),
		);

		$chrm = $doc->chromaticities;
		self::assertNotNull($chrm);
		self::assertEqualsWithDelta(0.3127, $chrm->whiteX, 0.000000001);
		self::assertEqualsWithDelta(0.329, $chrm->whiteY, 0.000000001);
		self::assertEqualsWithDelta(0.64, $chrm->redX, 0.000000001);
		self::assertEqualsWithDelta(0.33, $chrm->redY, 0.000000001);
		self::assertEqualsWithDelta(0.30, $chrm->greenX, 0.000000001);
		self::assertEqualsWithDelta(0.60, $chrm->greenY, 0.000000001);
		self::assertEqualsWithDelta(0.15, $chrm->blueX, 0.000000001);
		self::assertEqualsWithDelta(0.06, $chrm->blueY, 0.000000001);
		self::assertEqualsWithDelta(0.3127, $doc->toArray()['cHRM']['white']['x'], 0.000001);
		self::assertEqualsWithDelta(0.329, $doc->toArray()['cHRM']['white']['y'], 0.000001);
	}

	#[Test]
	public function wrong_chrm_length_follows_the_policy(): void
	{
		$png = PngBuilder::create()->addChunk('cHRM', \str_repeat("\0", 31))->build();

		self::assertNull(PngMetadataReader::create()->read($png)->chromaticities);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('cHRM payload must be exactly 32 bytes');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function it_decodes_iccp_header_and_desc_description(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addIccp('ICC Profile', PngBuilder::iccProfile('sRGB IEC61966-2.1'))
				->build(),
		);

		$profile = $doc->iccProfile;
		self::assertNotNull($profile);
		self::assertSame('ICC Profile', $profile->name);
		self::assertSame('sRGB IEC61966-2.1', $profile->description);
		self::assertSame('4.3.0', $profile->version);
		self::assertSame('mntr', $profile->profileClass);
		self::assertSame('RGB', $profile->colorSpace);
		self::assertSame('XYZ', $profile->pcs);
		self::assertSame(RenderingIntent::Perceptual, $profile->renderingIntent);
		self::assertSame('sRGB IEC61966-2.1', $doc->toArray()['iCCP']['description'] ?? null);
	}

	#[Test]
	public function it_decodes_iccp_mluc_description(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addIccp('Perfil', PngBuilder::iccProfile('Perfil en español', useMluc: true))
				->build(),
		);

		self::assertSame('Perfil en español', $doc->iccProfile?->description);
	}

	#[Test]
	public function an_iccp_profile_without_a_description_is_valid(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addIccp('No description', PngBuilder::iccProfile(withDescription: false))
				->build(),
		);

		self::assertNotNull($doc->iccProfile);
		self::assertNull($doc->iccProfile->description);
		self::assertArrayNotHasKey('description', $doc->toArray()['iCCP']);
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function malformedIccpCases(): iterable
	{
		$profile = PngBuilder::iccProfile();

		yield 'missing separator' => [
			'NoSeparator',
			'iCCP payload has no null terminator',
		];
		yield 'empty name' => [
			"\0\x00" . \gzcompress($profile),
			'iCCP profile name must be 1-79 bytes, 0 given',
		];
		yield 'oversized name' => [
			\str_repeat('K', 80) . "\0\x00" . \gzcompress($profile),
			'iCCP profile name must be 1-79 bytes, 80 given',
		];
		yield 'missing method' => [
			"ICC\0",
			'iCCP chunk is missing the compression method byte',
		];
		yield 'unsupported method' => [
			"ICC\0\x01" . \gzcompress($profile),
			'iCCP compression method must be 0 (zlib), 1 given',
		];
		yield 'corrupt zlib' => [
			"ICC\0\x00not-zlib-data",
			'zlib inflation failed or exceeded',
		];
	}

	#[Test]
	#[DataProvider('malformedIccpCases')]
	public function malformed_iccp_follows_the_policy(string $payload, string $message): void
	{
		$png = PngBuilder::create()->addChunk('iCCP', $payload)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage($message);
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function short_iccp_profile_follows_the_policy(): void
	{
		$png = PngBuilder::create()->addIccp('ICC', 'too-short')->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC profile is shorter than the 132-byte header');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_profile_without_acsp_signature_follows_the_policy(): void
	{
		$profile = \substr_replace(PngBuilder::iccProfile(), 'xxxx', 36, 4);
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('missing the "acsp" signature');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_profile_with_an_oversized_declared_size_follows_the_policy(): void
	{
		$profile = PngBuilder::iccProfile();
		$profile = \substr_replace($profile, \pack('N', \strlen($profile) + 1), 0, 4);
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC profile declares');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_profile_with_out_of_bounds_tags_follows_the_policy(): void
	{
		$profile = PngBuilder::iccProfile();
		$profile = \substr_replace($profile, \pack('N', 9999), 136, 4); // tag offset
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC tag entry points outside the declared profile size');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_profile_with_an_out_of_range_rendering_intent_follows_the_policy(): void
	{
		$profile = \substr_replace(PngBuilder::iccProfile(), \pack('N', 9), 64, 4);
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC profile rendering intent must be 0-3');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_profile_over_the_inflation_budget_follows_the_policy(): void
	{
		$png = PngBuilder::create()
			->addIccp('ICC', PngBuilder::iccProfile())
			->build();

		self::assertNull(
			PngMetadataReader::create(maxInflatedBytes: 64)->read($png)->iccProfile,
		);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('inflation budget');
		PngMetadataReader::create(strict: true, maxInflatedBytes: 64)->read($png);
	}

	#[Test]
	public function iccp_profile_name_of_79_bytes_is_accepted(): void
	{
		$name = \str_repeat('K', 79);
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addIccp($name, PngBuilder::iccProfile())->build(),
		);

		self::assertSame($name, $doc->iccProfile?->name);
	}

	#[Test]
	public function iccp_version_uses_major_minor_and_bugfix_nibbles(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addIccp('ICC', PngBuilder::iccProfile(version: 0x05350000))
				->build(),
		);

		self::assertSame('5.3.5', $doc->iccProfile?->version);
	}

	#[Test]
	public function compact_desc_tag_at_the_ascii_boundary_is_valid(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addIccp('ICC', PngBuilder::iccProfile('Hi', compactDesc: true))
				->build(),
		);

		self::assertSame('Hi', $doc->iccProfile?->description);
	}

	#[Test]
	public function empty_mluc_description_is_valid(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addIccp('ICC', PngBuilder::iccProfile('', useMluc: true))
				->build(),
		);

		self::assertNotNull($doc->iccProfile);
		self::assertNull($doc->iccProfile->description);
	}

	#[Test]
	public function mluc_description_is_trimmed(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addIccp('ICC', PngBuilder::iccProfile("Hi\0", useMluc: true))
				->build(),
		);

		self::assertSame('Hi', $doc->iccProfile?->description);
	}

	#[Test]
	public function two_tag_profile_with_description_second(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addIccp('ICC', PngBuilder::iccProfile(withExtraTag: true, descriptionSecond: true))
				->build(),
		);

		self::assertSame('sRGB IEC61966-2.1', $doc->iccProfile?->description);
	}

	#[Test]
	public function iccp_profile_shorter_than_132_bytes_follows_the_policy(): void
	{
		$png = PngBuilder::create()
			->addIccp('ICC', \substr(PngBuilder::iccProfile(), 0, 131))
			->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC profile is shorter than the 132-byte header');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_declared_size_below_132_follows_the_policy(): void
	{
		$profile = \substr_replace(PngBuilder::iccProfile(), \pack('N', 131), 0, 4);
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC profile declares 131 bytes');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_rendering_intent_is_read_from_offset_64(): void
	{
		$profile = \substr_replace(PngBuilder::iccProfile(), "\x09", 68, 1);
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addIccp('ICC', $profile)->build(),
		);

		self::assertSame(RenderingIntent::Perceptual, $doc->iccProfile?->renderingIntent);
	}

	#[Test]
	public function iccp_tag_offset_inside_the_tag_table_follows_the_policy(): void
	{
		$profile = \substr_replace(PngBuilder::iccProfile(), \pack('N', 140), 136, 4);
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC tag entry points outside the declared profile size');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_tag_offset_one_byte_inside_the_tag_table_follows_the_policy(): void
	{
		$profile = \substr_replace(PngBuilder::iccProfile(), \pack('N', 143), 136, 4);
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC tag entry points outside the declared profile size');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_tag_length_of_eight_reaches_the_description_parser(): void
	{
		$profile = \substr_replace(PngBuilder::iccProfile(), \pack('N', 8), 140, 4);
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC desc tag is too short');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_desc_tag_with_zero_ascii_count_is_valid(): void
	{
		$profile = PngBuilder::iccProfile();
		$profile = \substr_replace($profile, \pack('N', 12), 140, 4); // tag length
		$profile = \substr_replace($profile, \pack('N', 0), 152, 4);  // ASCII count

		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addIccp('ICC', $profile)->build(),
		);

		self::assertNotNull($doc->iccProfile);
		self::assertNull($doc->iccProfile->description);
	}

	#[Test]
	public function iccp_desc_ascii_count_beyond_the_tag_follows_the_policy(): void
	{
		$profile = PngBuilder::iccProfile();
		$unpacked = \unpack('N', \substr($profile, 140, 4));
		$tagLength = $unpacked === false ? 0 : $unpacked[1];
		$profile = \substr_replace($profile, \pack('N', $tagLength - 9), 152, 4);

		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC desc tag string exceeds the tag length');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_mluc_with_no_records_follows_the_policy(): void
	{
		$profile = PngBuilder::iccProfile('Desc', useMluc: true);
		$profile = \substr_replace($profile, \pack('N', 0), 144 + 8, 4); // record count
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC mluc tag has no usable records');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function iccp_mluc_string_outside_the_tag_follows_the_policy(): void
	{
		$profile = PngBuilder::iccProfile('Desc', useMluc: true);
		$profile = \substr_replace($profile, \pack('N', 1000), 144 + 16 + 4, 4); // string length
		$png = PngBuilder::create()->addIccp('ICC', $profile)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->iccProfile);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('ICC mluc string exceeds the tag length');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function duplicate_color_chunks_follow_the_singleton_policy(): void
	{
		$gama = PngBuilder::create()->addGama(45455)->addGama(45455)->build();
		self::assertNotNull(PngMetadataReader::create()->read($gama)->gamma);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('only one gAMA chunk is allowed');
		PngMetadataReader::create(strict: true)->read($gama);
	}

	#[Test]
	public function duplicate_iccp_chunks_follow_the_singleton_policy(): void
	{
		$profile = PngBuilder::iccProfile();
		$png = PngBuilder::create()
			->addIccp('ICC', $profile)
			->addIccp('ICC again', $profile)
			->build();

		self::assertSame('ICC', PngMetadataReader::create()->read($png)->iccProfile?->name);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('only one iCCP chunk is allowed');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function duplicate_chrm_chunks_follow_the_singleton_policy(): void
	{
		$png = PngBuilder::create()
			->addChrm(31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000)
			->addChrm(31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000)
			->build();

		self::assertNotNull(PngMetadataReader::create()->read($png)->chromaticities);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('only one cHRM chunk is allowed');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function color_precedence_prefers_iccp(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addGama(45455)
				->addChrm(31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000)
				->addSrgb(1)
				->addIccp('ICC', PngBuilder::iccProfile())
				->build(),
		);

		self::assertSame(ColorSource::Iccp, $doc->colorSource);
		self::assertSame(RenderingIntent::RelativeColorimetric, $doc->renderingIntent);
		self::assertNotNull($doc->iccProfile);
		self::assertNotNull($doc->gamma);
		self::assertNotNull($doc->chromaticities);
	}

	#[Test]
	public function color_precedence_prefers_srgb_over_explicit_chromaticities(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addGama(45455)
				->addChrm(31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000)
				->addSrgb(0)
				->build(),
		);

		self::assertSame(ColorSource::Srgb, $doc->colorSource);
	}

	#[Test]
	public function color_precedence_falls_back_to_chromaticities(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->addGama(45455)
				->addChrm(31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000)
				->build(),
		);

		self::assertSame(ColorSource::Chromaticities, $doc->colorSource);
		self::assertSame('cHRM+gAMA', $doc->toArray()['colorSource']);
	}

	#[Test]
	public function gamma_alone_is_not_a_color_source(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->addGama(45455)->build(),
		);

		self::assertNotNull($doc->gamma);
		self::assertNull($doc->colorSource);
		self::assertArrayNotHasKey('colorSource', $doc->toArray());
	}

	#[Test]
	public function no_color_chunks_yields_no_color_source(): void
	{
		$doc = PngMetadataReader::create()->read(PngBuilder::create()->build());

		self::assertNull($doc->colorSource);
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
	public function it_decodes_grayscale_trns(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->withIhdr(10, 10, 8, 0)->addTrns("\x00\x80")->build(),
		);

		self::assertSame(128, $doc->transparency?->gray);
		self::assertSame(['gray' => 128], $doc->toArray()['tRNS']);
	}

	#[Test]
	public function it_decodes_truecolor_trns(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()
				->withIhdr(10, 10, 8, 2)
				->addTrns(\pack('nnn', 65535, 0, 32768))
				->build(),
		);

		self::assertSame(
			['red' => 65535, 'green' => 0, 'blue' => 32768],
			$doc->transparency?->toArray(),
		);
	}

	#[Test]
	public function it_decodes_palette_trns_in_index_order(): void
	{
		$doc = PngMetadataReader::create()->read(
			PngBuilder::create()->withIhdr(10, 10, 8, 3)->addTrns("\x00\x80\xFF")->build(),
		);

		self::assertSame([0, 128, 255], $doc->transparency?->alpha);
		self::assertSame(['alpha' => [0, 128, 255]], $doc->toArray()['tRNS']);
	}

	#[Test]
	public function palette_trns_accepts_one_and_256_alpha_bytes(): void
	{
		$one = PngMetadataReader::create()->read(
			PngBuilder::create()->withIhdr(10, 10, 8, 3)->addTrns("\x00")->build(),
		);
		self::assertSame([0], $one->transparency?->alpha);

		$all = PngMetadataReader::create()->read(
			PngBuilder::create()
				->withIhdr(10, 10, 8, 3)
				->addTrns(\str_repeat("\x7F", 256))
				->build(),
		);
		self::assertCount(256, $all->transparency?->alpha ?? []);
	}

	/**
	 * @return iterable<string, array{int, string, int}>
	 */
	public static function shortTrnsCases(): iterable
	{
		yield 'grayscale needs 2' => [0, "\x80", 2];
		yield 'truecolor needs 6' => [2, "\x01\x02", 6];
	}

	#[Test]
	#[DataProvider('shortTrnsCases')]
	public function short_trns_payload_follows_the_policy(int $colorType, string $payload, int $required): void
	{
		$png = PngBuilder::create()->withIhdr(10, 10, 8, $colorType)->addTrns($payload)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->transparency);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage(\sprintf('tRNS needs at least %d byte', $required));
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function trailing_trns_bytes_are_tolerated_only_in_lenient_mode(): void
	{
		$png = PngBuilder::create()->withIhdr(10, 10, 8, 0)->addTrns("\x00\x80\x00")->build();

		self::assertSame(128, PngMetadataReader::create()->read($png)->transparency?->gray);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('tRNS payload must be exactly 2 byte');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function empty_palette_trns_follows_the_policy(): void
	{
		$png = PngBuilder::create()->withIhdr(10, 10, 8, 3)->addTrns('')->build();

		self::assertNull(PngMetadataReader::create()->read($png)->transparency);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('tRNS palette alpha table must contain 1-256 bytes, 0 given');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function oversized_palette_trns_follows_the_policy(): void
	{
		$png = PngBuilder::create()
			->withIhdr(10, 10, 8, 3)
			->addTrns(\str_repeat("\0", 257))
			->build();

		self::assertNull(PngMetadataReader::create()->read($png)->transparency);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('tRNS palette alpha table must contain 1-256 bytes, 257 given');
		PngMetadataReader::create(strict: true)->read($png);
	}

	/**
	 * @return iterable<string, array{int, string}>
	 */
	public static function alphaColorTypes(): iterable
	{
		yield 'grayscale with alpha' => [4, "\x00\x80"];
		yield 'rgb with alpha' => [6, \pack('nnn', 1, 2, 3)];
	}

	#[Test]
	#[DataProvider('alphaColorTypes')]
	public function trns_is_forbidden_for_alpha_color_types(int $colorType, string $payload): void
	{
		$png = PngBuilder::create()->withIhdr(10, 10, 8, $colorType)->addTrns($payload)->build();

		self::assertNull(PngMetadataReader::create()->read($png)->transparency);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('tRNS is not permitted for color type');
		PngMetadataReader::create(strict: true)->read($png);
	}

	#[Test]
	public function trns_without_header_raises_a_domain_failure_not_a_logic_crash(): void
	{
		$payload = "\x00\x80";
		$stream = \fopen('php://temp', 'r+b');
		self::assertIsResource($stream);
		\fwrite($stream, $payload);
		\rewind($stream);

		$chunk = new RawChunk(ChunkType::fromBytes('tRNS'), \strlen($payload), 0, 0, $stream);

		try {
			$this->expectException(ChunkDecodingException::class);
			$this->expectExceptionMessage('tRNS cannot be interpreted before the IHDR chunk');
			(new TrnsDecoder())->decode($chunk, new DecodingContext(), new MetadataBuilder());
		} finally {
			\fclose($stream);
		}
	}

	#[Test]
	public function duplicate_trns_follows_the_singleton_policy(): void
	{
		$png = PngBuilder::create()
			->withIhdr(10, 10, 8, 0)
			->addTrns("\x00\x80")
			->addTrns("\x00\x01")
			->build();

		self::assertSame(128, PngMetadataReader::create()->read($png)->transparency?->gray);

		$this->expectException(ChunkDecodingException::class);
		$this->expectExceptionMessage('only one tRNS chunk is allowed');
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
			['IHDR', 'tEXt', 'zTXt', 'iTXt', 'eXIf', 'sRGB', 'iCCP', 'gAMA', 'cHRM', 'bKGD', 'tRNS'],
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
