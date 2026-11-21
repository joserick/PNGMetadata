<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\IccProfile;
use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\Parsing\RawChunk;

/**
 * Decodes iCCP chunks: a Latin-1 profile name, compression method 0 and a
 * zlib-compressed ICC profile (spec section 11.3.2.3). Only the profile header
 * and its desc/mluc description tag are interpreted; the compressed profile is
 * inflated through the reader's shared budget.
 */
final class IccpDecoder implements ChunkDecoderInterface
{
	private const HEADER_SIZE = 128;
	private const TAG_ENTRY_SIZE = 12;

	public function supports(): string
	{
		return 'iCCP';
	}

	public function decode(RawChunk $chunk, DecodingContext $context, MetadataBuilder $builder): void
	{
		$data = $chunk->data();
		$separator = \strpos($data, "\0");

		if ($separator === false) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'iCCP payload has no null terminator after the profile name.',
			);
		}

		$name = \substr($data, 0, $separator);
		if ($name === '' || \strlen($name) > 79) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('iCCP profile name must be 1-79 bytes, %d given.', \strlen($name)),
			);
		}

		$methodOffset = $separator + 1;
		if ($methodOffset >= \strlen($data)) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				'iCCP chunk is missing the compression method byte.',
			);
		}

		$method = \ord($data[$methodOffset]);
		if ($method !== 0) {
			throw ChunkDecodingException::forChunk(
				$chunk->getCode(),
				$chunk->dataOffset,
				\sprintf('iCCP compression method must be 0 (zlib), %d given.', $method),
			);
		}

		$profile = $context->inflater->inflate(
			\substr($data, $methodOffset + 1),
			$chunk->getCode(),
			$chunk->dataOffset,
		);

		$builder->setIccProfile(
			self::parseProfile($profile, TextDecoder::latin1ToUtf8($name), $chunk),
			$chunk->getCode(),
			$chunk->dataOffset,
		);
	}

	private static function parseProfile(string $profile, string $name, RawChunk $chunk): IccProfile
	{
		$length = \strlen($profile);
		if ($length < self::HEADER_SIZE + 4) {
			throw self::failure($chunk, 'ICC profile is shorter than the 132-byte header and tag count.');
		}

		$declaredSize = self::uint32($profile, 0, $chunk);
		if ($declaredSize < self::HEADER_SIZE + 4 || $declaredSize > $length) {
			throw self::failure(
				$chunk,
				\sprintf('ICC profile declares %d bytes but the inflated payload has %d.', $declaredSize, $length),
			);
		}

		if (\substr($profile, 36, 4) !== 'acsp') {
			throw self::failure($chunk, 'ICC profile header is missing the "acsp" signature.');
		}

		$intentRaw = self::uint32($profile, 64, $chunk);
		$intent = RenderingIntent::fromByte($intentRaw);
		if ($intent === null) {
			throw self::failure(
				$chunk,
				\sprintf('ICC profile rendering intent must be 0-3, %d given.', $intentRaw),
			);
		}

		$tagCount = self::uint32($profile, self::HEADER_SIZE, $chunk);
		$tagTableEnd = self::HEADER_SIZE + 4 + ($tagCount * self::TAG_ENTRY_SIZE);
		if ($tagTableEnd > $declaredSize) {
			throw self::failure($chunk, 'ICC tag table does not fit inside the declared profile size.');
		}

		$description = null;
		for ($i = 0; $i < $tagCount; $i++) {
			$entry = self::HEADER_SIZE + 4 + ($i * self::TAG_ENTRY_SIZE);
			$signature = \substr($profile, $entry, 4);
			$offset = self::uint32($profile, $entry + 4, $chunk);
			$tagLength = self::uint32($profile, $entry + 8, $chunk);

			if ($tagLength < 8 || $offset < $tagTableEnd || $offset + $tagLength > $declaredSize) {
				throw self::failure($chunk, 'ICC tag entry points outside the declared profile size.');
			}

			if ($description === null && ($signature === 'desc' || $signature === 'mluc')) {
				$description = self::parseDescription($profile, $offset, $tagLength, $signature, $chunk);
			}
		}

		return new IccProfile(
			name: $name,
			description: $description,
			version: self::formatVersion(self::uint32($profile, 8, $chunk)),
			profileClass: self::signature($profile, 12),
			colorSpace: self::signature($profile, 16),
			pcs: self::signature($profile, 20),
			renderingIntent: $intent,
		);
	}

	private static function parseDescription(
		string $profile,
		int $offset,
		int $length,
		string $signature,
		RawChunk $chunk,
	): ?string {
		if ($signature === 'desc') {
			if ($length < 12) {
				throw self::failure($chunk, 'ICC desc tag is too short.');
			}
			$asciiCount = self::uint32($profile, $offset + 8, $chunk);
			if ($asciiCount > $length - 12) {
				throw self::failure($chunk, 'ICC desc tag string exceeds the tag length.');
			}
			$text = \rtrim(\substr($profile, $offset + 12, $asciiCount), "\0");

			return $text === '' ? null : TextDecoder::latin1ToUtf8($text);
		}

		if ($length < 28) {
			throw self::failure($chunk, 'ICC mluc tag is too short.');
		}
		$recordCount = self::uint32($profile, $offset + 8, $chunk);
		$recordSize = self::uint32($profile, $offset + 12, $chunk);
		if ($recordCount < 1 || $recordSize < 12) {
			throw self::failure($chunk, 'ICC mluc tag has no usable records.');
		}

		$record = $offset + 16;
		if ($record + 12 > $offset + $length) {
			throw self::failure($chunk, 'ICC mluc record exceeds the tag length.');
		}
		$stringLength = self::uint32($profile, $record + 4, $chunk);
		$stringOffset = self::uint32($profile, $record + 8, $chunk);
		if ($stringOffset + $stringLength > $length) {
			throw self::failure($chunk, 'ICC mluc string exceeds the tag length.');
		}

		$raw = \substr($profile, $offset + $stringOffset, $stringLength);
		$text = \rtrim(\mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE'), "\0");

		return $text === '' ? null : $text;
	}

	private static function formatVersion(int $version): string
	{
		return \sprintf(
			'%d.%d.%d',
			($version >> 24) & 0xFF,
			($version >> 20) & 0xF,
			($version >> 16) & 0xF,
		);
	}

	private static function signature(string $profile, int $offset): string
	{
		return \rtrim(\substr($profile, $offset, 4), " \0");
	}

	private static function uint32(string $profile, int $offset, RawChunk $chunk): int
	{
		$unpacked = \unpack('N', \substr($profile, $offset, 4));
		if ($unpacked === false) {
			throw self::failure($chunk, 'could not unpack an ICC profile integer.');
		}

		/** @var array{1: int} $unpacked */

		return $unpacked[1];
	}

	private static function failure(RawChunk $chunk, string $reason): ChunkDecodingException
	{
		return ChunkDecodingException::forChunk($chunk->getCode(), $chunk->dataOffset, $reason);
	}
}
