<?php

declare(strict_types=1);

namespace PNGMetadata\Exception;

use RuntimeException;

/**
 * Thrown when a chunk payload cannot be decoded according to the PNG specification.
 */
class ChunkDecodingException extends RuntimeException implements PngMetadataException
{
}
