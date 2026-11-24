<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * Immutable, typed view over the metadata decoded from a PNG datastream.
 */
final readonly class MetadataDocument
{
	/**
	 * @param TextEntry[] $texts Entries in file order; repeated keywords are preserved.
	 * @param array<string, mixed>|null $xmp Structured XMP properties grouped by namespace.
	 * @param array<string, mixed>|null $exif EXIF sections as returned by exif_read_data().
	 * @param string|null $exifRaw Raw TIFF payload backing thumbnailBytes().
	 */
	public function __construct(
		public ?HeaderData $header,
		public array $texts,
		public ?array $xmp,
		public ?array $exif,
		public ?string $exifRaw,
		public ?RenderingIntent $renderingIntent,
		public ?IccProfile $iccProfile,
		public ?Gamma $gamma,
		public ?Chromaticities $chromaticities,
		public ?ColorSource $colorSource,
		public ?BackgroundColor $background,
		public ?Transparency $transparency,
	) {
	}

	/**
	 * Serialize to a plain array using the typed structure.
	 *
	 * Sections that were not decoded are omitted rather than emitted as null;
	 * texts keep their file order.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$out = [];
		if ($this->header !== null) {
			$out['IHDR'] = $this->header->toArray();
		}
		if ($this->renderingIntent !== null) {
			$out['sRGB'] = $this->renderingIntent->label();
		}
		if ($this->colorSource !== null) {
			$out['colorSource'] = $this->colorSource->label();
		}
		if ($this->iccProfile !== null) {
			$out['iCCP'] = $this->iccProfile->toArray();
		}
		if ($this->gamma !== null) {
			$out['gAMA'] = $this->gamma->value();
		}
		if ($this->chromaticities !== null) {
			$out['cHRM'] = $this->chromaticities->toArray();
		}
		if ($this->background !== null) {
			$out['bKGD'] = $this->background->toString();
		}
		if ($this->transparency !== null) {
			$out['tRNS'] = $this->transparency->toArray();
		}
		if ($this->exif !== null) {
			$out['exif'] = $this->exif;
		}
		if ($this->xmp !== null) {
			$out['xmp'] = $this->xmp;
		}
		if ($this->texts !== []) {
			$out['texts'] = \array_map(
				static fn (TextEntry $t): array => \array_filter([
					'keyword' => $t->keyword,
					'text' => $t->text,
					'language' => $t->languageTag,
					'translatedKeyword' => $t->translatedKeyword,
					'compressed' => $t->compressed ?: null,
					'chunk' => $t->chunkType,
				], static fn ($v): bool => $v !== null),
				$this->texts,
			);
		}

		return $out;
	}

	/**
	 * Embedded EXIF thumbnail as raw image bytes, or null when the profile has
	 * no thumbnail. Returned as bytes, never as a GD resource (ADR-005).
	 */
	public function thumbnailBytes(): ?string
	{
		if ($this->exifRaw === null) {
			return null;
		}
		$stream = @\fopen('php://temp', 'r+b');
		if ($stream === false) {
			return null;
		}
		\fwrite($stream, $this->exifRaw);
		\rewind($stream);
		$thumb = @\exif_thumbnail($stream);
		\fclose($stream);

		return $thumb === false ? null : $thumb;
	}
}
