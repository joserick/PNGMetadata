<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

/**
 * Maps chunk type codes to their decoders. Unknown chunk types are simply
 * not decoded (they remain available as raw chunks from the parser).
 *
 * Supporting a new chunk is one decoder class plus one line in defaults();
 * the reader never special-cases chunk types (ADR-003).
 */
final class ChunkDecoderRegistry
{
	/** @var array<string, ChunkDecoderInterface> */
	private array $decoders = [];

	/**
	 * @param iterable<ChunkDecoderInterface>|null $decoders Null registers the default set.
	 */
	public function __construct(?iterable $decoders = null)
	{
		foreach ($decoders ?? self::defaults() as $decoder) {
			$this->register($decoder);
		}
	}

	/**
	 * Registration order is load-bearing: IHDR comes first because dependent
	 * decoders (bKGD, and later tRNS) need the context it writes.
	 *
	 * @return ChunkDecoderInterface[]
	 */
	public static function defaults(): array
	{
		return [
			new IhdrDecoder(),
			new TextDecoder(),
			new ItxtDecoder(),
			new ExifDecoder(),
			new SrgbDecoder(),
			new BkgdDecoder(),
		];
	}

	/**
	 * Register (or replace) the decoder for the code it declares.
	 */
	public function register(ChunkDecoderInterface $decoder): void
	{
		$this->decoders[$decoder->supports()] = $decoder;
	}

	/**
	 * The decoder for a chunk type, or null when the type is not interpreted.
	 */
	public function decoderFor(string $chunkType): ?ChunkDecoderInterface
	{
		return $this->decoders[$chunkType] ?? null;
	}
}
