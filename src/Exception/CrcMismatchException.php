<?php

declare(strict_types=1);

namespace PNGMetadata\Exception;

use RuntimeException;

/**
 * Thrown in strict mode when a chunk's computed CRC-32 does not match its stored CRC.
 */
class CrcMismatchException extends RuntimeException implements PngMetadataException
{
}
