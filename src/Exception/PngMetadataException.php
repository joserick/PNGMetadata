<?php

declare(strict_types=1);

namespace PNGMetadata\Exception;

/**
 * Common interface for all exceptions thrown by the PNGMetadata core.
 *
 * Allows consumers (e.g., framework integrations) to catch any library
 * error with a single catch, without losing the concrete exception type.
 */
interface PngMetadataException extends \Throwable
{
}
