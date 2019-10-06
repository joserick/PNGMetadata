<?php

namespace PNGMetadata;

use ArrayObject;
use Exception;
use PNGMetadata\Namespaces;

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
 * PNG Metadata class for extraction of XMP, TEXIF, EXIF, BKGD, RBG and IHDR.
 *
 * Returns the complete information found in the different types
 * of metadata within a PNG format image.
 * 
 * PHP version 7 (or higher)
 *
 * @category png-metadata
 * @package  PNGMetadata
 * @author   José Erick Carreón Gómez <joserick.92@gmail.com>
 * @copyright (c) 2019 José Erick Carreón Gómez
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public Licence (GPLv3)
 * @version 0.0.1
 * 
 * @return  array
 */
class PNGMetadata extends ArrayObject {

	/**
	 * The list of metadata.
	 *
	 * @var array
	 * @access public
	 */
	private $_metadata = [];

	/**
	 * The list of data chunks.
	 *
	 * Potential keys are 'tEXt' and/or 'iTXt'.
	 *
	 * @var array
	 * @access private
	 */
	private $_chunks = [];

	/**
	 * Initializes the functions required for metadata extraction.
	 * 
	 * @param string $path Location of the image in disk.
	 */
	function __construct( $path = null ) {

		$this->extractChunks( $path );
		$this->extractXMP();
		$this->extractTExif();
		$this->extractExif();
		$this->extractBKGD();
		$this->extractRBG();
		$this->extractIHDR();

		ksort( $this->_metadata );

		parent:: __construct( $this->_metadata );

	}

	/**
	 * Return a new PNGMetadata.
	 *
	 * In case of an error in the extraction of metadata this will return false.
	 * 
	 * @access public
	 * @param string $path Location of the image in disk.
	 *
	 * @return PNGMetadata|false
	 */
	public static function extract( $path = null ) {

		try {

			return new PNGMetadata( $path );
			
		} catch ( Exception $e ) {

			return false;

		}

	}

	/**
	 * Return metadata as array.
	 *
	 * Return the property '_metadata' which is an array.
	 * 
	 * @access public
	 * @param string $path Location of the image in disk.
	 * 
	 * @return array
	 */
	public function toArray() {

		return $this->_metadata;

	}

	/**
	 * Return a metadata specific.
	 *
	 * @access public
	 * @param string $string A string with structure, e.g. 'exif:THUMBNAIL:Compression'.
	 *
	 * @return string|array|false
	 */
	public function get( $string ) {

		if ( is_string( $string ) ) {

			$array_value = & $this->_metadata;
			$keys = explode( ':', $string );

			foreach ( $keys as $key ) {

				if ( isset( $array_value[ $key ] ) ) {

					$array_value = & $array_value[ $key ];

				} else {

					return false;

				}

			}

			return $array_value;

		}

		return false;

	}

	/**
	 * Return metadata as string.
	 *
	 * Return the metadata with a string structura of two colums.
	 * 
	 * @access public
	 * 
	 * @return string.
	 */
	public function __toString() {

		$data  = $this->printVertical();
		$max_len = max( array_map( 'strlen', array_keys( $data ) ) ) + 10;
		$strings[] = str_pad( '--Metadata--', $max_len ) . '--Value--' . "\n";

		foreach ( $data as $key => $value ) {

			$strings[] = str_pad( trim( $key ), $max_len ) . $value . "\n";

		}

		if ( php_sapi_name() !== 'cli' ) {

			array_unshift( $strings, '<pre>' );
			$strings[] = '</pre>';

		}

		return implode( ' ', $strings );

	}

	/**
	 * Join the metadata keys until they reach their value.
	 *
	 * @access private
	 * @param string last_key Key string from the previous array.
	 * @param string $array   Value from the previous array that is a array.
	 *
	 * @return array
	 */
	private function printVertical( $last_key = '', $array = null ) {

		$colums = [];

		if ( $last_key ) {

			$last_key .= ':';

		}

		foreach ( ( ( $array ) ? $array : $this->_metadata ) as $key => $value) {

			if ( is_array( $value ) ) {

				$colums[] = $this->printVertical(  $last_key . $key, $value );

			} else {

				$colums[] = [ $last_key . $key => $value ];

			}

		}

		return array_merge( ...$colums );

	}

	/**
	 * Extract the data chunks more important.
	 * 
	 * @access private
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 * @throws Exception If the provided argument is not a PNG image. 
	 * 
	 * @param  string $path Location of the image in disk.
	 * @return void
	 */
	private function extractChunks( $path ) {

		$content = fopen( $path, 'r' );

		if ( "\x89PNG\x0d\x0a\x1a\x0a" !== fread( $content, 8 ) ) {

			throw new Exception( 'Invalid PNG file signature' );

		}

		$chunkHeader = fread( $content, 8 );

		while ( $chunkHeader ) {

			$chunk = unpack( 'Nsize/a4type', $chunkHeader );

			if ( $chunk[ 'type' ] == 'IEND' ) break;

			if ( $chunk[ 'type' ] == 'tEXt' ) {

				$this->_chunks[ $chunk[ 'type' ] ][] = explode( "\0", fread( $content, $chunk[ 'size' ] ) );
				fseek( $content, 4, SEEK_CUR );

			} else {

				if ($chunk[ 'type' ] == 'eXIf' || $chunk[ 'type' ] == 'sRGB' || $chunk[ 'type' ] == 'iTXt' ||
					$chunk[ 'type' ] == 'bKGD' ) {

				$lastoffset = ftell( $content );
				$this->_chunks[$chunk[ 'type' ]] = fread( $content, $chunk[ 'size' ] );
				fseek( $content, $lastoffset, SEEK_SET );

			} elseif ( $chunk[ 'type' ] == 'IHDR' ) {

				$lastoffset = ftell( $content );

				for ( $i=0; $i < 6; $i++ ) {

					$this->_chunks[ $chunk[ 'type' ] ][] = fread( $content, ( ($i > 1) ? 1 : 4 ) );

				}

				fseek( $content, $lastoffset, SEEK_SET );

			}

			fseek( $content, $chunk[ 'size' ] + 4, SEEK_CUR );

		}

		$chunkHeader = fread( $content, 8 );
	}

	fclose($content);

}


	/**
	 * Extract IHDR type from iHDR chunk as a array.
	 * 
	 * @access private
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * 
	 * @return void
	 */
	private function extractIHDR() {

		if ( isset( $this->_chunks[ 'IHDR' ] ) ) {

			$ihdr = [

				'ImageWidth',
				'ImageHeight',
				'BitDepth',
				[
					0 => 'Grayscale',
					2 => 'RGB',
					3 => 'Palette',
					4 => 'Grayscale with Alpha',
					6 => 'RGB with Alpha',
					8 => 'ColorType'
				], [
					0 => 'Deflate/Inflate',
					8 => 'Compression'
				], [
					0 => 'Adaptive',
					8 => 'Filter'
				], [
					0 => 'Noninterlaced',
					1 => 'Adam7 Interlace',
					8 => 'Interlace'
				]

			];

			foreach ( $this->_chunks[ 'IHDR' ] as $key => $value ) {

				if ( $key > 1 ) {

					if ( $key == 2 ) {

						$this->_metadata[ 'IHDR' ][ $ihdr[ $key ] ] = ord( $value );

					} else {

						$this->_metadata[ 'IHDR' ][ $ihdr[ $key ][ 8 ] ] = $ihdr[ $key ][ ord( $value ) ];

					}

				} else {

					$this->_metadata[ 'IHDR' ][ $ihdr[ $key ] ] = unpack( 'Ni', $value )['i'];

				}

			}

		}

	}

	/**
	 * Extract KGD type from bKGD chunk as a array.
	 * 
	 * @access private
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * 
	 * @return void
	 */
	private function extractBKGD() {

		if ( isset( $this->_chunks[ 'bKGD' ] ) ) {

			$this->_metadata[ 'bKGD' ] = join( " ", unpack( strlen(
				$this->_chunks[ 'bKGD' ] ) < 2 ? 'C' : 'n*', $this->_chunks[ 'bKGD' ] ) );

		}

	}


	/**
	 * Extract RBG type from sRBG chunk as a array.
	 * 
	 * @access private
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * 
	 * @return void
	 */
	private function extractRBG() {

		if ( isset( $this->_chunks[ 'sRGB' ] ) ) {

			$rbg = [ 'Perceptual', 'Relative Colorimetric', 'Saturation', 'Absolute Colorimetric' ];
			$this->_metadata[ 'sRBG' ] = $rbg[ end( ... [ unpack( 'C', $this->_chunks[ 'sRGB' ] ) ] ) ];

		}

	}

	/**
	 * Extract Exif data from eXIf chunk as a array.
	 * 
	 * @access private
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * 
	 * @return void
	 */
	private function extractExif() {

		if ( isset ( $this->_chunks[ 'eXIf' ] ) ) {

			$this->_metadata[ 'exif' ] = array_replace( $this->_metadata[ 'exif' ],
				exif_read_data( 'data://image/jpeg;base64,' . base64_encode( $this->_chunks[ 'eXIf' ] ) ) );

		}

	}

	/**
	 * Extract Exif data form tEXt chunk as a array.
	 * 
	 * @access private
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * 
	 * @return void
	 */
	private function extractTExif() {

		if ( isset( $this->_chunks[ 'tEXt' ] ) && is_array( $this->_chunks[ 'tEXt' ] ) ) {

			foreach ( $this->_chunks[ 'tEXt' ] as $exif ) {

				list( $group, $tag, $tag2 ) = array_pad( explode( ':', $exif[ 0 ] ), 3, null );

				if ( $tag == 'thumbnail' ) $tag = strtoupper( $tag );

				$this->_metadata[ $group ][ $tag ] = ( ( $tag2 ) ? [ $tag2 => $exif[ 1 ] ] : $exif[ 1 ] );

			}

		}

	}

	/**
	 * Extract XMP data from iTXt chunk as a array.
	 * 
	 * @access private
	 * @see PNGMetadata::$_chunks     For the property whose chunks data are storage.
	 * @see PNGMetadata::$metadata   For the property whose metadata are storage.
	 * @see PNGMetadata::extractRDF() To read a xml looking for metadata.
	 * 
	 * @throws Exception If the iTXt chunck has not 'x:xmpmeta' string.
	 * 
	 * @return void
	 */
	private function extractXMP() {

		if ( isset( $this->_chunks[ 'iTXt' ] ) && strncmp( $this->_chunks[ 'iTXt' ], 'XML:com.adobe.xmp', 17 ) === 0 ) {

			$dom = new \DomDocument( '1.0', 'UTF-8' );
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = false;
			$dom->substituteEntities = false;

			$dom->loadXML( ltrim( substr( $this->_chunks[ 'iTXt' ], 17 ), "\x00" ) );
			$dom->encoding = 'UTF-8';

			if ( 'x:xmpmeta' !== $dom->documentElement->nodeName ) {

				error_log( 'ExtractRoot node must be of type x:xmpmeta.' );
				return false;

			}

			$xpath = new \DOMXPath( $dom );

			$namespaces = new Namespaces();

			foreach ( $namespaces->getURIs() as $prefix => $url ) {

				$xpath->registerNamespace( $prefix, $url );

			}


			foreach ( $namespaces->getURIs() as $prefix => $url ) {
				
				foreach ( [ '', '@' ] as $char ) {

					$description = $xpath->query(
						sprintf( "//rdf:Description[%s*[namespace-uri()='$url']]", $char ) );

					if ( $description->length > 0 ) {

						$rdfDesc = $description->item( 0 );
						break;

					}

				}

				if ( ! isset( $rdfDesc ) ) continue;

				$result = $this->extractRDF( $xpath->query( $prefix.':*', $rdfDesc ), $xpath );

				if ( ! empty( $result ) ) {

					$this->_metadata[$prefix] = $result;

				}

			}

		}

	}

	/**
	 * Extract metadata from a XML(XMP) as a array.
	 * 
	 * @access private
	 * @see PNGMetadata::extractRDF() To read a xml looking for metadata.
	 *
	 * @param  array $result   Parameter list of an XML node.
	 * @param  DOMXPath $xpath Class DOMXPath.
	 * 
	 * @return array
	 */
	private function extractRDF( $result, $xpath ) {

		$data = [];

		if ( $result->length ) {

			for ( $j=0; $j < $result->length; $j++ ) {

				$node = $result->item( $j );

				if ( $node ) {

					$xrdf = $xpath->query( 'rdf:*', $node )->item( 0 );

					if ( $xrdf ) {

						if ( $xrdf->localName == 'Alt' || $xrdf->localName == 'Bag' ) {

							$data[ $node->localName ] = $xrdf->childNodes->item( 0 )->nodeValue;

						} else {

							for ($i = 0; $i < $xrdf->childNodes->length; $i++) {
								
								$items = $this->extractRDF( $xpath->query( $node->prefix.':*', $xrdf->childNodes->item( $i ) ), $xpath );

								if ( ! empty( $items ) ) {

									$data[ $node->localName ] = $items;

								}

							}

						}

					} else {

						$data[ $node->localName ] = $node->textContent;

					}

				}

			}

		}

		return $data;

	}

}
