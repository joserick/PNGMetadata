<?php

declare(strict_types=1);

namespace PNGMetadata\Exception;

use RuntimeException;

/**
 * Thrown when the datastream ends before the declared chunk data is complete.
 */
class TruncatedDataException extends RuntimeException implements PngMetadataException
{
}
