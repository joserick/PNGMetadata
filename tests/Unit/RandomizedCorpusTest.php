<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit;

use PNGMetadata\Chunk\BkgdDecoder;
use PNGMetadata\Chunk\ChrmDecoder;
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
use PNGMetadata\Chunk\XmpDecoder;
use PNGMetadata\Chunk\ZtxtDecoder;
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
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Property-style testing: well-formed PNGs built with randomized, shuffled
 * ancillary chunks must ALWAYS extract fully in lenient mode, with every
 * decoded value matching the inputs in file order. Seeded engines keep the
 * suite deterministic; expectations are recomputed from the same operations
 * that built the stream, so a shuffle never invalidates an assertion.
 */
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
final class RandomizedCorpusTest extends TestCase
{
	/**
	 * @return iterable<string, array{int}>
	 */
	public static function seeds(): iterable
	{
		foreach ([1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 101, 202, 303, 404, 505] as $seed) {
			yield 'seed-' . $seed => [$seed];
		}
	}

	#[Test]
	#[DataProvider('seeds')]
	public function well_formed_random_streams_always_extract(int $seed): void
	{
		$randomizer = new Randomizer(new Mt19937($seed));

		$expectedTexts = [];
		$ops = [];

		foreach (['alpha', 'beta'] as $tag) {
			$ops[] = function (PngBuilder $builder) use ($randomizer, $tag, &$expectedTexts): void {
				$keyword = $tag . \str_repeat('x', $randomizer->getInt(0, 12));
				$expectedTexts[] = [$keyword, 'value-' . $keyword];
				$builder->addText($keyword, 'value-' . $keyword);
			};
		}

		$ops[] = function (PngBuilder $builder) use (&$expectedTexts): void {
			$expectedTexts[] = ['Note', 'Texto con acentos — unicode'];
			$builder->addItxt('Note', 'Texto con acentos — unicode', 'es', 'Nota');
		};

		$ops[] = function (PngBuilder $builder) use (&$expectedTexts): void {
			$expectedTexts[] = ['Compressed', 'Café zTXt'];
			$builder->addZtxt('Compressed', "Caf\xe9 zTXt");
		};

		$expectedIntent = null;
		$ops[] = function (PngBuilder $builder) use ($randomizer, &$expectedIntent): void {
			$byte = $randomizer->getInt(0, 3);
			$expectedIntent = RenderingIntent::fromByte($byte);
			$builder->addSrgb($byte);
		};

		$ops[] = function (PngBuilder $builder): void {
			$builder->addGama(45455);
		};

		$ops[] = function (PngBuilder $builder): void {
			$builder->addChrm(31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000);
		};

		$ops[] = function (PngBuilder $builder): void {
			$builder->addIccp('ICC', PngBuilder::iccProfile());
		};

		$expectedBackground = null;
		$ops[] = function (PngBuilder $builder) use ($randomizer, &$expectedBackground): void {
			$rgb = [$randomizer->getInt(0, 65535), $randomizer->getInt(0, 65535), $randomizer->getInt(0, 65535)];
			$expectedBackground = BackgroundColor::rgb($rgb[0], $rgb[1], $rgb[2]);
			$builder->addBkgd(\pack('nnn', ...$rgb));
		};

		$ops[] = function (PngBuilder $builder) use ($randomizer): void {
			// Unknown ancillary chunk (spec 5.4 example): must never error.
			$builder->addChunk('vpAg', "\x00\x00\x00\x00\x03" . $randomizer->getBytes(4));
		};

		$ops[] = function (PngBuilder $builder) use ($randomizer): void {
			$builder->addIdat($randomizer->getInt(8, 128));
		};

		$builder = PngBuilder::create()->withIhdr(4, 4, 8, 6);

		foreach ($randomizer->shuffleArray($ops) as $op) {
			$op($builder);
		}

		$doc = PngMetadataReader::create()->read($builder->build());

		self::assertSame(4, $doc->header?->width);
		self::assertCount(\count($expectedTexts), $doc->texts);
		self::assertSame(
			$expectedTexts,
			\array_map(static fn (TextEntry $t): array => [$t->keyword, $t->text], $doc->texts),
		);
		self::assertSame($expectedIntent, $doc->renderingIntent);
		self::assertSame($expectedBackground?->toString(), $doc->background?->toString());
		self::assertSame(45455, $doc->gamma?->scaled);
		self::assertNotNull($doc->chromaticities);
		self::assertSame('ICC', $doc->iccProfile?->name);
		self::assertSame(ColorSource::Iccp, $doc->colorSource);
	}
}
