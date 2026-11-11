<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Unit\Parsing;

use PNGMetadata\Parsing\ChunkType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChunkType::class)]
final class ChunkTypeTest extends TestCase
{
	#[Test]
	public function critical_chunks_report_critical_public_valid_reserved_unsafe(): void
	{
		$type = ChunkType::fromBytes('IHDR');

		self::assertTrue($type->isCritical());
		self::assertFalse($type->isAncillary());
		self::assertFalse($type->isPrivate());
		self::assertTrue($type->hasValidReservedBit());
		self::assertFalse($type->isSafeToCopy());
	}

	#[Test]
	public function vp_ag_reports_the_spec_documented_flag_combination(): void
	{
		// Spec section 5.4 example: lowercase v/p = ancillary + private,
		// uppercase A = valid reserved bit, lowercase g = safe-to-copy.
		$type = ChunkType::fromBytes('vpAg');

		self::assertTrue($type->isAncillary());
		self::assertTrue($type->isPrivate());
		self::assertTrue($type->hasValidReservedBit());
		self::assertTrue($type->isSafeToCopy());
	}

	#[Test]
	public function text_chunks_are_ancillary_public_valid_reserved_safe_to_copy(): void
	{
		$type = ChunkType::fromBytes('tEXt');

		self::assertTrue($type->isAncillary());
		self::assertFalse($type->isPrivate());
		self::assertTrue($type->hasValidReservedBit());
		self::assertTrue($type->isSafeToCopy());
	}

	#[Test]
	public function srgb_is_ancillary_despite_its_uppercase_fourth_letter(): void
	{
		// Only the first letter decides criticality: "sRGB" is ancillary even
		// though its last letter is uppercase (spec section 5.4).
		$type = ChunkType::fromBytes('sRGB');

		self::assertTrue($type->isAncillary());
		self::assertFalse($type->isCritical());
	}

	#[Test]
	public function lowercase_third_letter_invalidates_the_reserved_bit(): void
	{
		$type = ChunkType::fromBytes('vpag');

		self::assertFalse($type->hasValidReservedBit());
	}

	#[Test]
	public function from_bytes_requires_exactly_four_bytes(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		ChunkType::fromBytes('IHD');
	}

	#[Test]
	public function equals_compares_the_four_letter_code(): void
	{
		$type = ChunkType::fromBytes('IEND');

		self::assertTrue($type->equals('IEND'));
		self::assertFalse($type->equals('IHDR'));
		self::assertSame('IEND', $type->code);
	}

	#[Test]
	public function only_the_five_structural_critical_types_are_recognized(): void
	{
		foreach (['IHDR', 'PLTE', 'IDAT', 'IEND', 'fdAT'] as $code) {
			self::assertTrue(ChunkType::fromBytes($code)->isRecognizedCritical(), $code);
		}

		// Unknown critical and any ancillary type are not recognized-critical.
		self::assertFalse(ChunkType::fromBytes('ZsTr')->isRecognizedCritical());
		self::assertFalse(ChunkType::fromBytes('vpAg')->isRecognizedCritical());
		self::assertFalse(ChunkType::fromBytes('srgb')->isRecognizedCritical());
	}
}
