<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

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
	 * @param array<string, mixed> $xmp
	 */
	public function setXmp(array $xmp): void
	{
		$this->xmp = $xmp;
	}

	/**
	 * @param array<string, mixed> $exif
	 */
	public function setExif(array $exif, string $rawPayload): void
	{
		// Single eXIf chunk allowed; the first one wins.
		if ($this->exif !== null) {
			return;
		}
		$this->exif = $exif;
		$this->exifRaw = $rawPayload;
	}

	public function setRenderingIntent(RenderingIntent $intent): void
	{
		$this->renderingIntent = $intent;
	}

	public function setBackground(BackgroundColor $background): void
	{
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
}
