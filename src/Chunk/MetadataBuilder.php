<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\BackgroundColor;
use PNGMetadata\Metadata\HeaderData;
use PNGMetadata\Metadata\MetadataDocument;
use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\Metadata\TextEntry;

/**
 * Mutable accumulator used while decoding chunks; frozen into an immutable
 * MetadataDocument once the stream has been consumed.
 */
final class MetadataBuilder
{
	private ?HeaderData $header = null;

	/** @var TextEntry[] */
	private array $texts = [];

	/** @var array<string, mixed>|null */
	private ?array $xmp = null;

	/** @var array<string, mixed>|null */
	private ?array $exif = null;

	private ?string $exifRaw = null;

	private ?RenderingIntent $renderingIntent = null;

	private ?BackgroundColor $background = null;

	/** @var array<string, true> singletons already accepted (first wins) */
	private array $singletons = [];

	public function __construct(
		private readonly bool $strict = false,
	) {
	}

	public function setHeader(HeaderData $header): void
	{
		$this->header = $header;
	}

	public function getHeader(): ?HeaderData
	{
		return $this->header;
	}

	public function addText(TextEntry $entry): void
	{
		$this->texts[] = $entry;
	}

	/**
	 * The first *decodable* XMP packet claims the singleton slot even when it
	 * carries no extractable properties (null), so a later packet cannot
	 * replace it and strict mode reports the duplicate either way.
	 *
	 * @param array<string, mixed>|null $xmp
	 */
	public function setXmp(?array $xmp, string $chunkCode, int $offset): void
	{
		if (!$this->acceptsSingleton('XMP packet', $chunkCode, $offset)) {
			return;
		}
		$this->xmp = $xmp;
	}

	/**
	 * @param array<string, mixed> $exif
	 */
	public function setExif(array $exif, string $rawPayload, string $chunkCode, int $offset): void
	{
		if (!$this->acceptsSingleton('eXIf', $chunkCode, $offset)) {
			return;
		}
		$this->exif = $exif;
		$this->exifRaw = $rawPayload;
	}

	public function setRenderingIntent(RenderingIntent $intent, string $chunkCode, int $offset): void
	{
		if (!$this->acceptsSingleton('sRGB', $chunkCode, $offset)) {
			return;
		}
		$this->renderingIntent = $intent;
	}

	public function setBackground(BackgroundColor $background, string $chunkCode, int $offset): void
	{
		if (!$this->acceptsSingleton('bKGD', $chunkCode, $offset)) {
			return;
		}
		$this->background = $background;
	}

	public function build(): MetadataDocument
	{
		return new MetadataDocument(
			header: $this->header,
			texts: $this->texts,
			xmp: $this->xmp,
			exif: $this->exif,
			exifRaw: $this->exifRaw,
			renderingIntent: $this->renderingIntent,
			background: $this->background,
		);
	}

	/**
	 * Chunks the spec allows at most once keep their first decoded instance;
	 * later instances are a conformance violation - ignored in lenient mode,
	 * reported as a decode failure in strict mode.
	 */
	private function acceptsSingleton(string $key, string $chunkCode, int $offset): bool
	{
		if (!isset($this->singletons[$key])) {
			$this->singletons[$key] = true;

			return true;
		}

		if ($this->strict) {
			throw ChunkDecodingException::forChunk(
				$chunkCode,
				$offset,
				\sprintf('only one %s chunk is allowed; the first instance wins.', $key),
			);
		}

		return false;
	}
}
