<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Fixtures;

/**
 * Programmatic PNG fixture builder.
 *
 * Builds deterministic PNG datastreams in memory so tests do not depend on
 * third-party images or binary fixtures checked into the repository.
 */
final class PngBuilder
{
	public const SIGNATURE = "\x89PNG\x0d\x0a\x1a\x0a";

	private string $ihdr = '';

	/** @var string[] */
	private array $chunks = [];

	private function __construct()
	{
	}

	public static function create(): self
	{
		return new self();
	}

	public function withIhdr(
		int $width = 800,
		int $height = 600,
		int $bitDepth = 8,
		int $colorType = 6,
		int $compression = 0,
		int $filter = 0,
		int $interlace = 0,
	): self {
		$this->ihdr = \pack('NNCCCCC', $width, $height, $bitDepth, $colorType, $compression, $filter, $interlace);

		return $this;
	}

	public function addText(string $keyword, string $text): self
	{
		return $this->addChunk('tEXt', $keyword . "\0" . $text);
	}

	public function addZtxt(string $keyword, string $text, int $method = 0): self
	{
		return $this->addChunk('zTXt', $keyword . "\0" . \chr($method) . \gzcompress($text));
	}

	public function addItxt(
		string $keyword,
		string $text,
		string $languageTag = '',
		string $translatedKeyword = '',
		bool $compressed = false,
	): self {
		$payload = $keyword . "\0"
			. \chr($compressed ? 1 : 0)
			. \chr(0)
			. $languageTag . "\0"
			. $translatedKeyword . "\0"
			. ($compressed ? \gzcompress($text) : $text);

		return $this->addChunk('iTXt', $payload);
	}

	public function addXmp(string $xml): self
	{
		return $this->addItxt('XML:com.adobe.xmp', $xml);
	}

	public function addExif(string $payload): self
	{
		return $this->addChunk('eXIf', $payload);
	}

	public function addSrgb(int $intent): self
	{
		return $this->addChunk('sRGB', \chr($intent));
	}

	public function addGama(int $scaled): self
	{
		return $this->addChunk('gAMA', \pack('N', $scaled));
	}

	public function addChrm(
		int $whiteX,
		int $whiteY,
		int $redX,
		int $redY,
		int $greenX,
		int $greenY,
		int $blueX,
		int $blueY,
	): self {
		return $this->addChunk('cHRM', \pack('N8', $whiteX, $whiteY, $redX, $redY, $greenX, $greenY, $blueX, $blueY));
	}

	public function addIccp(string $name, string $profile, int $method = 0): self
	{
		return $this->addChunk('iCCP', $name . "\0" . \chr($method) . \gzcompress($profile));
	}

	public function addBkgd(string $payload): self
	{
		return $this->addChunk('bKGD', $payload);
	}

	public function addTrns(string $payload): self
	{
		return $this->addChunk('tRNS', $payload);
	}

	public function addIdat(int $size = 1024): self
	{
		return $this->addChunk('IDAT', \str_repeat("\x00", $size));
	}

	public function addChunk(string $type, string $data): self
	{
		$this->chunks[] = self::chunk($type, $data);

		return $this;
	}

	public function build(): string
	{
		$ihdr = $this->ihdr !== '' ? $this->ihdr : \pack('NNCCCCC', 800, 600, 8, 6, 0, 0, 0);

		return self::SIGNATURE
			. self::chunk('IHDR', $ihdr)
			. \implode('', $this->chunks)
			. self::chunk('IEND', '');
	}

	public static function chunk(string $type, string $data): string
	{
		return \pack('N', \strlen($data)) . $type . $data . \pack('N', \crc32($type . $data));
	}

	/**
	 * Minimal TIFF profile as carried by an eXIf chunk (the APP1 JPEG marker and
	 * "Exif\0\0" ID code are excluded per spec 11.3.4.5): header + IFD0 with a
	 * single Make entry + value area at byte 26. When a thumbnail payload is
	 * given, IFD1 is appended with the JPEGInterchangeFormat pair so
	 * ext-exif's exif_thumbnail() can recover it.
	 *
	 * @param bool $bigEndian Motorola ("MM\0*") byte order; Intel ("II*\0") by default.
	 */
	/**
	 * Minimal ICC profile for iCCP tests: a 128-byte header, one optional
	 * description tag (`desc` or `mluc`) and a correctly bounded tag table.
	 */
	public static function iccProfile(
		string $description = 'sRGB IEC61966-2.1',
		string $profileClass = 'mntr',
		string $colorSpace = 'RGB ',
		string $pcs = 'XYZ ',
		int $version = 0x04300000,
		int $renderingIntent = 0,
		bool $useMluc = false,
		bool $withDescription = true,
		bool $compactDesc = false,
		bool $withExtraTag = false,
		bool $descriptionSecond = false,
	): string {
		$header = \pack('N', 0)                                  // size, patched below
			. 'TEST'                                              // preferred CMM
			. \pack('N', $version)
			. \str_pad(\substr($profileClass, 0, 4), 4)
			. \str_pad(\substr($colorSpace, 0, 4), 4)
			. \str_pad(\substr($pcs, 0, 4), 4)
			. \str_repeat("\0", 12)                              // creation date
			. 'acsp'
			. \str_repeat("\0", 4)                               // platform
			. \pack('N', 0)                                      // flags
			. \str_repeat("\0", 4)                               // manufacturer
			. \str_repeat("\0", 4)                               // model
			. \str_repeat("\0", 8)                               // attributes
			. \pack('N', $renderingIntent)
			. \str_repeat("\0", 12)                              // illuminant
			. \str_repeat("\0", 4)                               // creator
			. \str_repeat("\0", 16)                              // profile ID
			. \str_repeat("\0", 28);                             // reserved

		$tagSignature = $useMluc ? 'mluc' : 'desc';
		$descriptionTag = $withDescription
			? ($useMluc ? self::mlucTag($description) : self::descTag($description, $compactDesc))
			: '';

		/** @var array<int, array{string, string}> $tags */
		$tags = [];
		if ($withExtraTag && !$descriptionSecond) {
			$tags[] = ['wtpt', self::xyzTag()];
		}
		if ($withDescription) {
			$tags[] = [$tagSignature, $descriptionTag];
		}
		if ($withExtraTag && $descriptionSecond) {
			$tags[] = ['wtpt', self::xyzTag()];
		}

		$tagOffset = 128 + 4 + (12 * \count($tags));
		$tagTable = \pack('N', \count($tags));
		$tagData = '';
		foreach ($tags as [$signature, $tag]) {
			$tagTable .= $signature . \pack('N', $tagOffset) . \pack('N', \strlen($tag));
			$padded = \str_pad($tag, (int) \ceil(\strlen($tag) / 4) * 4, "\0");
			$tagData .= $padded;
			$tagOffset += \strlen($padded);
		}

		$profile = $header . $tagTable . $tagData;

		return (string) \substr_replace($profile, \pack('N', \strlen($profile)), 0, 4);
	}

	private static function descTag(string $description, bool $compact = false): string
	{
		$ascii = $description . "\0";
		$head = 'desc' . "\0\0\0\0" . \pack('N', \strlen($ascii)) . $ascii;

		if ($compact) {
			return $head;
		}

		return $head
			. \pack('N', 0) . \pack('N', 0)     // Unicode language code + count
			. \pack('n', 0) . "\0"              // ScriptCode + count
			. \str_repeat("\0", 67);
	}

	private static function mlucTag(string $description): string
	{
		$utf16 = \mb_convert_encoding($description, 'UTF-16BE', 'UTF-8');
		$stringOffset = 16 + 12; // mluc header + one 12-byte record

		return 'mluc' . "\0\0\0\0"
			. \pack('N', 1) . \pack('N', 12)
			. \pack('n', 0x656E) . \pack('n', 0x5553) // en-US
			. \pack('N', \strlen($utf16)) . \pack('N', $stringOffset)
			. $utf16;
	}

	private static function xyzTag(): string
	{
		return 'XYZ ' . "\0\0\0\0" . \str_repeat("\0", 12);
	}

	public static function exifProfile(
		string $make = 'SONY',
		bool $bigEndian = false,
		?string $thumbnail = null,
	): string {
		$u16 = $bigEndian ? 'n' : 'v';
		$u32 = $bigEndian ? 'N' : 'V';
		$magic = $bigEndian ? "MM\x00\x2A" : "II\x2A\x00";

		$value = $make . "\0";
		$ifd0Size = 2 + 12 + 4;
		$makeOffset = 8 + $ifd0Size;               // 26
		$ifd1Offset = $makeOffset + \strlen($value);

		$ifd0 = \pack($u16, 1) // one entry
			. \pack($u16, 0x010F) . \pack($u16, 2) . \pack($u32, \strlen($value)) . \pack($u32, $makeOffset)
			. \pack($u32, $thumbnail === null ? 0 : $ifd1Offset); // next IFD

		if ($thumbnail === null) {
			return $magic . \pack($u32, 8) . $ifd0 . $value;
		}

		$thumbnailOffset = $ifd1Offset + 2 + 2 * 12 + 4;
		$ifd1 = \pack($u16, 2) // two entries: thumbnail offset + length
			. \pack($u16, 0x0201) . \pack($u16, 4) . \pack($u32, 1) . \pack($u32, $thumbnailOffset)
			. \pack($u16, 0x0202) . \pack($u16, 4) . \pack($u32, 1) . \pack($u32, \strlen($thumbnail))
			. \pack($u32, 0); // no further IFD

		return $magic . \pack($u32, 8) . $ifd0 . $value . $ifd1 . $thumbnail;
	}
}
