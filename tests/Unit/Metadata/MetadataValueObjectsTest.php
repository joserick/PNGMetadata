<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Metadata;

use PNGMetadata\Metadata\BackgroundColor;
use PNGMetadata\Metadata\Chromaticities;
use PNGMetadata\Metadata\ColorSource;
use PNGMetadata\Metadata\ColorType;
use PNGMetadata\Metadata\Gamma;
use PNGMetadata\Metadata\HeaderData;
use PNGMetadata\Metadata\IccProfile;
use PNGMetadata\Metadata\InterlaceMethod;
use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\Metadata\TextEntry;
use PNGMetadata\Metadata\Transparency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColorType::class)]
#[CoversClass(InterlaceMethod::class)]
#[CoversClass(RenderingIntent::class)]
#[CoversClass(BackgroundColor::class)]
#[CoversClass(HeaderData::class)]
#[CoversClass(TextEntry::class)]
#[CoversClass(Gamma::class)]
#[CoversClass(Chromaticities::class)]
#[CoversClass(IccProfile::class)]
#[CoversClass(ColorSource::class)]
#[CoversClass(Transparency::class)]
final class MetadataValueObjectsTest extends TestCase
{
	#[Test]
	public function bit_depth_matrix_matches_the_spec_table(): void
	{
		// Spec section 6.1: allowed combinations of bit depth and color type.
		self::assertSame([1, 2, 4, 8, 16], ColorType::Grayscale->allowedBitDepths());
		self::assertSame([8, 16], ColorType::RGB->allowedBitDepths());
		self::assertSame([1, 2, 4, 8], ColorType::Palette->allowedBitDepths());
		self::assertSame([8, 16], ColorType::GrayscaleWithAlpha->allowedBitDepths());
		self::assertSame([8, 16], ColorType::RGBWithAlpha->allowedBitDepths());

		self::assertTrue(ColorType::Grayscale->allowsBitDepth(1));
		self::assertFalse(ColorType::Palette->allowsBitDepth(16));
		self::assertFalse(ColorType::RGB->allowsBitDepth(4));
		self::assertFalse(ColorType::RGBWithAlpha->allowsBitDepth(2));
	}

	#[Test]
	public function color_type_labels_match_v1_wording(): void
	{
		self::assertSame('Grayscale', ColorType::Grayscale->label());
		self::assertSame('RGB', ColorType::RGB->label());
		self::assertSame('Palette', ColorType::Palette->label());
		self::assertSame('Grayscale with Alpha', ColorType::GrayscaleWithAlpha->label());
		self::assertSame('RGB with Alpha', ColorType::RGBWithAlpha->label());
	}

	#[Test]
	public function interlace_method_labels(): void
	{
		self::assertSame('Noninterlaced', InterlaceMethod::None->label());
		self::assertSame('Adam7 Interlace', InterlaceMethod::Adam7->label());
	}

	#[Test]
	public function rendering_intents_resolve_or_report_unknown(): void
	{
		self::assertSame('Perceptual', RenderingIntent::Perceptual->label());
		self::assertSame('Relative Colorimetric', RenderingIntent::RelativeColorimetric->label());
		self::assertSame('Saturation', RenderingIntent::Saturation->label());
		self::assertSame('Absolute Colorimetric', RenderingIntent::AbsoluteColorimetric->label());

		self::assertSame(RenderingIntent::Saturation, RenderingIntent::fromByte(2));
		self::assertNull(RenderingIntent::fromByte(4));
		self::assertNull(RenderingIntent::fromByte(255));
	}

	#[Test]
	public function background_color_stringifies_per_variant(): void
	{
		self::assertSame('7', BackgroundColor::paletteIndex(7)->toString());
		self::assertSame('128', BackgroundColor::grayscale(128)->toString());
		self::assertSame('65535 0 32768', BackgroundColor::rgb(65535, 0, 32768)->toString());
	}

	#[Test]
	public function gamma_exposes_the_scaled_and_decimal_values(): void
	{
		$gamma = new Gamma(45455);

		self::assertSame(45455, $gamma->scaled);
		self::assertEqualsWithDelta(0.45455, $gamma->value(), 0.000001);
	}

	#[Test]
	public function chromaticities_scale_and_serialize_the_white_point_and_primaries(): void
	{
		$chrm = Chromaticities::fromScaled(31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000);

		self::assertEqualsWithDelta(0.3127, $chrm->whiteX, 0.000001);
		self::assertEqualsWithDelta(0.329, $chrm->whiteY, 0.000001);
		self::assertSame([
			'white' => ['x' => $chrm->whiteX, 'y' => $chrm->whiteY],
			'red' => ['x' => $chrm->redX, 'y' => $chrm->redY],
			'green' => ['x' => $chrm->greenX, 'y' => $chrm->greenY],
			'blue' => ['x' => $chrm->blueX, 'y' => $chrm->blueY],
		], $chrm->toArray());
	}

	#[Test]
	public function icc_profile_serializes_header_fields_and_optional_description(): void
	{
		$profile = new IccProfile(
			name: 'ICC Profile',
			description: 'sRGB IEC61966-2.1',
			version: '4.3.0',
			profileClass: 'mntr',
			colorSpace: 'RGB',
			pcs: 'XYZ',
			renderingIntent: RenderingIntent::Perceptual,
		);

		self::assertSame([
			'name' => 'ICC Profile',
			'version' => '4.3.0',
			'class' => 'mntr',
			'colorSpace' => 'RGB',
			'pcs' => 'XYZ',
			'renderingIntent' => 'Perceptual',
			'description' => 'sRGB IEC61966-2.1',
		], $profile->toArray());

		$withoutDescription = new IccProfile(
			name: 'ICC Profile',
			description: null,
			version: '4.3.0',
			profileClass: 'mntr',
			colorSpace: 'RGB',
			pcs: 'XYZ',
			renderingIntent: RenderingIntent::Perceptual,
		);
		self::assertArrayNotHasKey('description', $withoutDescription->toArray());
	}

	#[Test]
	public function color_source_labels_match_the_chunk_names(): void
	{
		self::assertSame('iCCP', ColorSource::Iccp->label());
		self::assertSame('sRGB', ColorSource::Srgb->label());
		self::assertSame('cHRM+gAMA', ColorSource::Chromaticities->label());
	}

	#[Test]
	public function transparency_exposes_each_variant(): void
	{
		$gray = Transparency::grayscale(128);
		self::assertSame(128, $gray->gray);
		self::assertSame([], $gray->alpha);
		self::assertSame(['gray' => 128], $gray->toArray());

		$rgb = Transparency::rgb(255, 0, 128);
		self::assertSame(255, $rgb->red);
		self::assertSame(0, $rgb->green);
		self::assertSame(128, $rgb->blue);
		self::assertSame(['red' => 255, 'green' => 0, 'blue' => 128], $rgb->toArray());

		$palette = Transparency::palette([0, 128, 255]);
		self::assertSame([0, 128, 255], $palette->alpha);
		self::assertSame(['alpha' => [0, 128, 255]], $palette->toArray());

		$sparse = Transparency::palette([2 => 10, 5 => 20]);
		self::assertSame([10, 20], $sparse->alpha);
	}

	#[Test]
	public function header_to_array_exposes_all_seven_fields(): void
	{
		$header = new HeaderData(
			width: 800,
			height: 600,
			bitDepth: 8,
			colorType: ColorType::Palette,
			compressionMethod: 0,
			filterMethod: 0,
			interlaceMethod: InterlaceMethod::Adam7,
		);

		self::assertSame([
			'ImageWidth' => 800,
			'ImageHeight' => 600,
			'BitDepth' => 8,
			'ColorType' => 'Palette',
			'Compression' => 'Deflate/Inflate',
			'Filter' => 'Adaptive',
			'Interlace' => 'Adam7 Interlace',
		], $header->toArray());
	}

	#[Test]
	public function text_entry_defaults_are_text_shaped(): void
	{
		$entry = new TextEntry(keyword: 'Title', text: 'Hi');

		self::assertNull($entry->languageTag);
		self::assertNull($entry->translatedKeyword);
		self::assertFalse($entry->compressed);
		self::assertSame('tEXt', $entry->chunkType);
	}
}
