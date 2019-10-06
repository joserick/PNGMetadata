<?php

namespace PNGMetadata;

/**
 * PNG Metadata.
 * 
 * @category png-metadata
 * @package PNGMetadata
 * @author   <joserick.92@gmail.com> José Erick Carreón Gómez
 * @copyright (c) 2019 José Erick Carreón Gómez
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public Licence (GPLv3)
 * @version 0.0.1
 *
 * This file is part of photo-metadata.
 * 
 * PNGMetadata is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PNGMetadata is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * Prefix and URI of metadata reference tables.
 *
 * Returns a list with the Prefix and URI based in 'https://www.exiv2.org/tags-xmp-xmp.html'.
 *
 * @category uri-metadata-list
 * @package  PNGMetadata
 * @author   José Erick Carreón Gómez <joserick.92@gmail.com>
 * @copyright (c) 2019 José Erick Carreón Gómez
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public Licence (GPLv3)
 * @version 0.0.1
 * 
 * @return  array
 */
class Namespaces{

	private $namespaces = [

		'dc'              => 'http://purl.org/dc/elements/1.1/',
		'rdf'             => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
		'xmp'             => 'http://ns.adobe.com/xap/1.0/',
		'xmpRights'       => 'http://ns.adobe.com/xap/1.0/rights/',
		'xmpMM'           => 'http://ns.adobe.com/xap/1.0/mm/',
		'xmpBJ'           => 'http://ns.adobe.com/xap/1.0/bj/',
		'xmpTPg'          => 'http://ns.adobe.com/xap/1.0/t/pg/',
		'xmpDM'           => 'http://ns.adobe.com/xmp/1.0/DynamicMedia/',
		'pdf'             => 'http://ns.adobe.com/pdf/1.3/',
		'photoshop'       => 'http://ns.adobe.com/photoshop/1.0/',
		'crs'             => 'http://ns.adobe.com/camera-raw-settings/1.0/',
		'crss'            => 'http://ns.adobe.com/camera-raw-saved-settings/1.0/',
		'tiff'            => 'http://ns.adobe.com/tiff/1.0/',
		'exif'            => 'http://ns.adobe.com/exif/1.0/',
		'exifEX'          => 'http://cipa.jp/exif/1.0/',
		'aux'             => 'http://ns.adobe.com/exif/1.0/aux/',
		'Iptc4xmpCore'    => 'http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/',
		'Iptc4xmpExt'     => 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/',
		'plus'            => 'http://ns.useplus.org/ldf/xmp/1.0/',
		'mwg-rs'          => 'http://www.metadataworkinggroup.com/schemas/regions/',
		'mwg-kw'          => 'http://www.metadataworkinggroup.com/schemas/keywords/',
		'dwc'             => 'http://rs.tdwg.org/dwc/index.htm',
		'dcterms'         => 'http://purl.org/dc/terms/',
		'digiKam'         => 'http://www.digikam.org/ns/1.0/',
		'kipi'            => 'http://www.digikam.org/ns/kipi/1.0/',
		'GPano'           => 'http://ns.google.com/photos/1.0/panorama/',
		'lr'              => 'http://ns.adobe.com/lightroom/1.0/',
		'acdsee'          => 'http://ns.acdsee.com/iptc/1.0/',
		'mediapro'        => 'http://ns.iview-multimedia.com/mediapro/1.0/',
		'expressionmedia' => 'http://ns.microsoft.com/expressionmedia/1.0/',
		'MicrosoftPhoto'  => 'http://ns.microsoft.com/photo/1.0/',
		'MP'              => 'http://ns.microsoft.com/photo/1.2/',
		'MPRI'            => 'http://ns.microsoft.com/photo/1.2/t/RegionInfo#',
		'MPReg'           => 'http://ns.microsoft.com/photo/1.2/t/Region#'

	];

	public function getURIs() {

		return $this->namespaces;

	}

	public function getPrefix() {

		return array_keys( $this->namespaces );

	}

	public function get($prefix) {

		$this->namespaces[ $prefix ];

	}

}