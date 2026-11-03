<?php

declare(strict_types=1);

namespace PNGMetadata\Exception;

use InvalidArgumentException;

/**
 * Thrown when the input cannot be read or does not contain a PNG datastream.
 */
class InvalidPngException extends InvalidArgumentException implements PngMetadataException
{
}
