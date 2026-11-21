<?php

declare(strict_types=1);

namespace PNGMetadata\Metadata;

/**
 * The color chunk that supplies the image's effective color description after
 * applying the PNG §4.3 Table 1 precedence order.
 */
enum ColorSource: string
{
	case Iccp = 'iCCP';
	case Srgb = 'sRGB';
	case Chromaticities = 'cHRM+gAMA';

	public function label(): string
	{
		return $this->value;
	}
}
