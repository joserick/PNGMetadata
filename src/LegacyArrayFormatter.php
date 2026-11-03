<?php

declare(strict_types=1);

namespace PNGMetadata;

use PNGMetadata\Metadata\MetadataDocument;
use PNGMetadata\Metadata\TextEntry;

/**
 * Adapts the typed metadata document to the exact v1 array shape:
 * top-level groups, legacy key names, nesting, and ksort() ordering.
 */
final class LegacyArrayFormatter
{
	/**
	 * @return array<string, mixed>
	 */
	public function format(MetadataDocument $document): array
	{
		$metadata = [];

		$this->addTextGroups($metadata, $document->texts);

		if ($document->xmp !== null) {
			$metadata['xmp'] = $document->xmp;
		}

		if ($document->exif !== null) {
			$existing = $metadata['exif'] ?? [];
			$metadata['exif'] = \array_replace(\is_array($existing) ? $existing : [], $document->exif);
		}

		if ($document->background !== null) {
			$metadata['bKGD'] = $document->background->toString();
		}

		if ($document->renderingIntent !== null) {
			$metadata['sRGB'] = $document->renderingIntent->label();
		}

		if ($document->header !== null) {
			$header = $document->header->toArray();
			// v1 read only 6 of the 7 IHDR fields, so it never surfaced the
			// interlace method. Preserve that exact key set for parity.
			unset($header['Interlace']);
			$metadata['IHDR'] = $header;
		}

		\ksort($metadata);

		return $metadata;
	}

	/**
	 * Reproduces v1 tEXt grouping: "group:tag" or "group:tag:subtag" keywords
	 * are nested, and the literal keyword "thumbnail" is uppercased.
	 *
	 * @param array<string, mixed> $metadata
	 * @param TextEntry[] $texts
	 */
	private function addTextGroups(array &$metadata, array $texts): void
	{
		foreach ($texts as $entry) {
			if ($entry->chunkType !== 'tEXt') {
				continue;
			}
			[$group, $tag, $tag2] = \array_pad(\explode(':', $entry->keyword), 3, null);
			if (!\is_string($group) || !\is_string($tag)) {
				continue;
			}
			if ($tag === 'thumbnail') {
				$tag = 'THUMBNAIL';
			}
			if (!isset($metadata[$group]) || !\is_array($metadata[$group])) {
				$metadata[$group] = [];
			}
			/** @var array<string, mixed> $groupArray */
			$groupArray = &$metadata[$group];
			$groupArray[$tag] = $tag2 !== null ? [$tag2 => $entry->text] : $entry->text;
			unset($groupArray);
		}
	}
}
