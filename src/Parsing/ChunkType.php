<?php

declare(strict_types=1);

namespace PNGMetadata\Parsing;

/**
 * A PNG chunk type code (4 ASCII bytes).
 *
 * Bit 5 of each type byte carries a property flag (PNG spec, section 5.4):
 *  - byte 1: ancillary (lowercase) vs critical (uppercase)
 *  - byte 2: private (lowercase) vs public (uppercase)
 *  - byte 3: reserved — must be uppercase in conforming datastreams
 *  - byte 4: safe-to-copy (lowercase) vs unsafe-to-copy (uppercase)
 */
final readonly class ChunkType
{
	private function __construct(
		public string $code,
	) {
	}

	public static function fromBytes(string $bytes): self
	{
		if (\strlen($bytes) !== 4) {
			throw new \InvalidArgumentException('A chunk type must be exactly 4 bytes.');
		}

		return new self($bytes);
	}

	public function isAncillary(): bool
	{
		return $this->isLowercase(0);
	}

	public function isCritical(): bool
	{
		return !$this->isAncillary();
	}

	public function isPrivate(): bool
	{
		return $this->isLowercase(1);
	}

	/**
	 * The reserved bit is valid (uppercase) in conforming datastreams.
	 */
	public function hasValidReservedBit(): bool
	{
		return !$this->isLowercase(2);
	}

	public function isSafeToCopy(): bool
	{
		return $this->isLowercase(3);
	}

	public function equals(string $code): bool
	{
		return $this->code === $code;
	}

	private function isLowercase(int $offset): bool
	{
		return (\ord($this->code[$offset]) & 0x20) !== 0;
	}
}
