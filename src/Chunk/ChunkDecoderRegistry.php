<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

/**
 * Maps chunk type codes to their decoders. Unknown chunk types are simply
 * not decoded (they remain available as raw chunks from the parser).
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

	public function register(ChunkDecoderInterface $decoder): void
	{
		$this->decoders[$decoder->supports()] = $decoder;
	}

	public function decoderFor(string $chunkType): ?ChunkDecoderInterface
	{
		return $this->decoders[$chunkType] ?? null;
	}
}
