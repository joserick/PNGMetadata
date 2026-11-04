<?php

declare(strict_types=1);

namespace PNGMetadata\Tests\Integration;

use PNGMetadata\Exception\PngMetadataException;
use PNGMetadata\PngMetadataReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Ground-truth corpus (opt-in): points the suite at a locally downloaded
 * PngSuite - third-party images are never committed (licensing rule).
 *
 *   PNGSUITE_DIR=/path/to/pngsuite composer test:integration
 *
 * Contract per file (corpus-agnostic, deliberately not golden-value):
 * lenient read() yields a document with a header, or fails with a typed
 * domain exception - never a crash, never a non-domain throwable.
 */
#[CoversClass(PngMetadataReader::class)]
#[UsesClass(\PNGMetadata\Parsing\PngReader::class)]
#[UsesClass(\PNGMetadata\Parsing\RawChunk::class)]
#[UsesClass(\PNGMetadata\Parsing\ChunkType::class)]
#[UsesClass(\PNGMetadata\Parsing\BinaryStream::class)]
#[UsesClass(\PNGMetadata\Chunk\ChunkDecoderRegistry::class)]
#[UsesClass(\PNGMetadata\Chunk\DecodingContext::class)]
#[UsesClass(\PNGMetadata\Chunk\MetadataBuilder::class)]
#[UsesClass(\PNGMetadata\Metadata\MetadataDocument::class)]
final class PngSuiteTest extends TestCase
{
	#[\PHPUnit\Framework\Attributes\Test]
	public function every_pngsuite_member_is_parsed_or_fails_as_domain_error(): void
	{
		$dir = \getenv('PNGSUITE_DIR');

		if ($dir === false || $dir === '' || !\is_dir($dir)) {
			$this->markTestSkipped(
				'PNGSUITE_DIR is not set to a readable directory; the corpus suite is opt-in.',
			);
		}

		$files = \array_merge(
			\glob($dir . '/*.png') ?: [],
			\glob($dir . '/*/*.png') ?: [], // one level of subdirectories
		);

		if ($files === []) {
			self::fail(\sprintf('PNGSUITE_DIR="%s" contains no *.png files.', $dir));
		}

		$offenders = [];

		foreach ($files as $file) {
			try {
				$document = PngMetadataReader::create()->read($file);

				if ($document->header === null) {
					$offenders[] = \sprintf('%s: extracted without an IHDR header', \basename($file));
				} else {
					$serialized = $document->toArray();
					if (!\is_array($serialized)) {
						$offenders[] = \sprintf('%s: toArray() did not return an array', \basename($file));
					}
				}
			} catch (PngMetadataException) {
				// Corrupt members are expected in the corpus; typed failure is success.
			} catch (\Throwable $e) {
				$offenders[] = \sprintf('%s: %s: %s', \basename($file), $e::class, $e->getMessage());
			}
		}

		self::assertSame([], $offenders, \sprintf('%d corpus members checked.', \count($files)));
	}
}
