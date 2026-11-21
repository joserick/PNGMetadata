<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * ICC profile metadata extracted from an iCCP chunk. Only the profile header
 * and its description tag are interpreted; the rest of the profile remains a
 * binary payload outside this value object.
 */
final readonly class IccProfile
{
	public function __construct(
		public string $name,
		public ?string $description,
		public string $version,
		public string $profileClass,
		public string $colorSpace,
		public string $pcs,
		public RenderingIntent $renderingIntent,
	) {
	}

	/**
	 * Serializable view used by MetadataDocument::toArray().
	 *
	 * @return array<string, string>
	 */
	public function toArray(): array
	{
		$out = [
			'name' => $this->name,
			'version' => $this->version,
			'class' => $this->profileClass,
			'colorSpace' => $this->colorSpace,
			'pcs' => $this->pcs,
			'renderingIntent' => $this->renderingIntent->label(),
		];

		if ($this->description !== null) {
			$out['description'] = $this->description;
		}

		return $out;
	}
}
