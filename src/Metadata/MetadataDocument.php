<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * Immutable, typed view over the metadata decoded from a PNG datastream.
 */
final readonly class MetadataDocument
{
	/**
	 * @param TextEntry[] $texts
	 * @param array<string, mixed>|null $xmp
	 * @param array<string, mixed>|null $exif
	 */
	public function __construct(
		public ?HeaderData $header,
		public array $texts,
		public ?array $xmp,
		public ?array $exif,
		public ?string $exifRaw,
		public ?RenderingIntent $renderingIntent,
		public ?BackgroundColor $background,
	) {
	}

	/**
	 * Serialize to a plain array using the typed structure.
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
		if ($this->background !== null) {
			$out['bKGD'] = $this->background->toString();
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
	 * Embedded EXIF thumbnail as raw image bytes, when present.
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
