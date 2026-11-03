<?php

declare(strict_types=1);

namespace PNGMetadata\Parsing;

use PNGMetadata\Exception\InvalidPngException;

/**
 * Normalizes the supported input kinds (file path, binary string, stream
 * resource) into a seekable binary stream for the parser.
 */
final class BinaryStream
{
	/**
	 * @param string|resource $input File path, binary string, or open stream resource.
	 * @return resource A seekable binary stream, positioned at byte 0.
	 */
	public static function open(mixed $input): mixed
	{
		if (\is_resource($input)) {
			return self::fromResource($input);
		}

		if (!\is_string($input) || $input === '') {
			throw new InvalidPngException('The input must be a file path, a binary string, or a stream resource.');
		}

		if (@\is_file($input)) {
			return self::fromPath($input);
		}

		return self::fromString($input);
	}

	/**
	 * @return resource
	 */
	private static function fromPath(string $path): mixed
	{
		if (!\is_readable($path)) {
			throw new InvalidPngException(\sprintf('The file "%s" is not readable.', $path));
		}
		$stream = @\fopen($path, 'rb');
		if ($stream === false) {
			throw new InvalidPngException(\sprintf('The file "%s" could not be opened.', $path));
		}

		return $stream;
	}

	/**
	 * @return resource
	 */
	private static function fromString(string $bytes): mixed
	{
		$stream = \fopen('php://temp', 'r+b');
		if ($stream === false) {
			throw new InvalidPngException('Could not allocate a temporary stream.');
		}
		\fwrite($stream, $bytes);
		\rewind($stream);

		return $stream;
	}

	/**
	 * @param resource $resource
	 * @return resource
	 */
	private static function fromResource(mixed $resource): mixed
	{
		if (\get_resource_type($resource) !== 'stream') {
			throw new InvalidPngException('The provided resource is not a stream.');
		}
		$meta = \stream_get_meta_data($resource);
		if ($meta['seekable']) {
			\rewind($resource);

			return $resource;
		}

		// Non-seekable streams (pipes, sockets) are buffered into php://temp.
		$copy = \fopen('php://temp', 'r+b');
		if ($copy === false) {
			throw new InvalidPngException('Could not allocate a temporary stream.');
		}
		if (\stream_copy_to_stream($resource, $copy) === false) {
			throw new InvalidPngException('Could not read from the provided stream.');
		}
		\rewind($copy);

		return $copy;
	}
}
