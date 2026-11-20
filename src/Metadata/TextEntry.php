<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * A textual metadata entry from a tEXt, zTXt or iTXt chunk.
 */
final readonly class TextEntry
{
	/**
	 * @param string $keyword Keyword as stored (Latin-1 → UTF-8 for tEXt/zTXt).
	 * @param string $text Text value (UTF-8).
	 * @param string|null $languageTag BCP47 language tag (iTXt only).
	 * @param string|null $translatedKeyword Translated keyword (iTXt only).
	 * @param bool $compressed Whether the text was zlib-compressed (zTXt/iTXt).
	 * @param string $chunkType Originating chunk code: 'tEXt', 'zTXt' or 'iTXt'.
	 */
	public function __construct(
		public string $keyword,
		public string $text,
		public ?string $languageTag = null,
		public ?string $translatedKeyword = null,
		public bool $compressed = false,
		public string $chunkType = 'tEXt',
	) {
	}
}
