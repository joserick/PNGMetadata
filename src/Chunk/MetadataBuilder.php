<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use PNGMetadata\Exception\ChunkDecodingException;
use PNGMetadata\Metadata\BackgroundColor;
use PNGMetadata\Metadata\Chromaticities;
use PNGMetadata\Metadata\ColorSource;
use PNGMetadata\Metadata\Gamma;
use PNGMetadata\Metadata\HeaderData;
use PNGMetadata\Metadata\IccProfile;
use PNGMetadata\Metadata\MetadataDocument;
use PNGMetadata\Metadata\RenderingIntent;
use PNGMetadata\Metadata\TextEntry;

/**
 * The single accumulator used while decoding chunks (ADR-003): decoders never
 * talk to each other, they only write here. Frozen into an immutable
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

	private ?IccProfile $iccProfile = null;

	private ?Gamma $gamma = null;

	private ?Chromaticities $chromaticities = null;

	private ?BackgroundColor $background = null;

	/** @var array<string, true> singletons already accepted (first wins) */
	private array $singletons = [];

	public function __construct(
		private readonly bool $strict = false,
	) {
	}

	/**
	 * Written by IhdrDecoder; the header also reaches dependent decoders
	 * through DecodingContext.
	 */
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

	public function setIccProfile(IccProfile $profile, string $chunkCode, int $offset): void
	{
		if (!$this->acceptsSingleton('iCCP', $chunkCode, $offset)) {
			return;
		}
		$this->iccProfile = $profile;
	}

	public function setGamma(Gamma $gamma, string $chunkCode, int $offset): void
	{
		if (!$this->acceptsSingleton('gAMA', $chunkCode, $offset)) {
			return;
		}
		$this->gamma = $gamma;
	}

	public function setChromaticities(Chromaticities $chromaticities, string $chunkCode, int $offset): void
	{
		if (!$this->acceptsSingleton('cHRM', $chunkCode, $offset)) {
			return;
		}
		$this->chromaticities = $chromaticities;
	}

	public function setBackground(BackgroundColor $background, string $chunkCode, int $offset): void
	{
		if (!$this->acceptsSingleton('bKGD', $chunkCode, $offset)) {
			return;
		}
		$this->background = $background;
	}

	/**
	 * Freeze the accumulated state into the document exposed to consumers.
	 */
	public function build(): MetadataDocument
	{
		return new MetadataDocument(
			header: $this->header,
			texts: $this->texts,
			xmp: $this->xmp,
			exif: $this->exif,
			exifRaw: $this->exifRaw,
			renderingIntent: $this->renderingIntent,
			iccProfile: $this->iccProfile,
			gamma: $this->gamma,
			chromaticities: $this->chromaticities,
			colorSource: $this->resolveColorSource(),
			background: $this->background,
		);
	}

	/**
	 * Apply PNG §4.3 Table 1 to the chunks supported in this phase:
	 * iCCP > sRGB > cHRM+gAMA. gAMA alone is a gamma hint, not a full color
	 * source, so it does not produce a resolved source on its own.
	 */
	private function resolveColorSource(): ?ColorSource
	{
		if ($this->iccProfile !== null) {
			return ColorSource::Iccp;
		}
		if ($this->renderingIntent !== null) {
			return ColorSource::Srgb;
		}
		if ($this->chromaticities !== null) {
			return ColorSource::Chromaticities;
		}

		return null;
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
