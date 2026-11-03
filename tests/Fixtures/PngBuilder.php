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

	public function addBkgd(string $payload): self
	{
		return $this->addChunk('bKGD', $payload);
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
}
