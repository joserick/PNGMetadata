<?php

declare(strict_types=1);

namespace PNGMetadata;

use ArrayObject;

/**
 * PNG Metadata.
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
 *
 * PNG Metadata class for extraction of XMP, TEXIF, EXIF, BKGD, sRGB and IHDR.
 *
 * Returns the complete information found in the different types
 * of metadata within a PNG format image.
 */
class PNGMetadata extends ArrayObject
{
	/** The file path PNG */
	private ?string $path;

	/**
	 * The list of metadata.
	 *
	 * @var mixed[]
	 */
	private array $metadata = [];

	/**
	 * Exif data (jpg) in in a memory stream..
	 *
	 * @var resource|null
	 */
	private $exif_data;

	/**
	 * The list of data chunks.
	 * Potential keys are 'tEXt' and/or 'iTXt'.
	 *
	 * @var mixed[]
	 */
	private array $chunks = [];

	/**
	 * The list of XMP tags to remove.
	 * Tags that are not necessary to be seen in the output but if its values.
	 *
	 * @var string[]
	 */
	private array $tagsXMP = [
		'dc', 'xmp', 'xmpRights', 'xmpMM', 'xmpBJ', 'xmpTPg', 'xmpDM', 'pdf', 'photoshop',
		'crs', 'crss', 'tiff', 'exif', 'exifEX', 'aux', 'Iptc4xmpCore', 'Iptc4xmpExt',
		'plus', 'mwg-rs', 'mwg-kw', 'dwc', 'dcterms', 'digiKam', 'kipi', 'GPano', 'lr',
		'acdsee', 'mediapro', 'expressionmedia', 'MicrosoftPhoto', 'MP', 'MPRI', 'MPReg',
	];

	/**
	 * The list of XMP prefix and suffix to remove.
	 * The prefix and suffix that are not necessary to be seen in the output but if its values.
	 *
	 * @var string[]
	 */
	private array $prefSuffXMP = ['stRef', 'rdf', 'li', 'Alt', 'stEvt', 'Bag', 'Seq', 'crs'];


	/**
	 * Initializes the functions required for metadata extraction.
	 *
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 *
	 * @param string $path Location of the image in disk.
	 */
	public function __construct(string $path)
	{
		$this->checkPath($path);
		$this->extractChunks();
		$this->extractXMP();
		$this->extractTExif();
		$this->extractExif();
		$this->extractBKGD();
		$this->extractRGB();
		$this->extractIHDR();
		ksort($this->metadata);

		parent::__construct($this->metadata);
	}


	/**
	 * Return a new PNGMetadata.
	 *
	 * @param string $path Location of the image in disk.
	 * @return PNGMetadata|null
	 */
	public static function extract(?string $path = null): ?self
	{
		try {
			return new self($path);
		} catch (\Throwable $e) {
			return null;
		}
	}


	/**
	 * Return metadata as array.
	 *
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return $this->metadata;
	}


	/**
	 * Return a metadata specific.
	 *
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 *
	 * @param string $string A string with structure, e.g. 'exif:THUMBNAIL:Compression'.
	 * @return string|mixed[]|null
	 */
	public function get(string $string)
	{
		$return = &$this->metadata;
		foreach (explode(':', $string) as $key) {
			if (isset($return[$key])) {
				$return = &$return[$key];
			} else {
				return null;
			}
		}

		return $return;
	}


	/**
	 * Return the metadata with a string structura of two colums.
	 */
	public function __toString(): string
	{
		$data = $this->printVertical();
		$max_len = max(array_map('strlen', array_keys($data))) + 10;
		$strings[] = str_pad('--Metadata--', $max_len) . '--Value--' . "\n";

		foreach ($data as $key => $value) {
			$strings[] = str_pad(trim($key), $max_len) . $value . "\n";
		}
		if (PHP_SAPI !== 'cli') {
			array_unshift($strings, '<pre>');
			$strings[] = '</pre>';
		}

		return implode(' ', $strings);
	}


	/**
	 * Check if file path is a PNG.
	 *
	 * @param  string $path Location of the image in disk.
	 */
	public static function isPNG(string $path): bool
	{
		return self::getType($path) == IMAGETYPE_PNG;
	}


	/**
	 * Get image type.
	 *
	 * @throws \InvalidArgumentException If argument path is empty or if the file path does not exist.
	 *
	 * @param  string $path Location of the image in disk.
	 * @return int|false
	 */
	public static function getType(string $path)
	{
		if (!$path) {
			throw new \InvalidArgumentException('The argument path is empty', 101);
		} elseif (!file_exists($path)) {
			throw new \InvalidArgumentException('The file path does not exist or it\'s inaccessible', 102);
		}

		return exif_imagetype($path);
	}


	/**
	 * Get thumbnail resource.
	 *
	 * @see PNGMetadata::$exif_data Efix data formatted.
	 *
	 * @return \GdImage|false
	 */
	public function getThumbnail()
	{
		// Check if the Exif data is not null.
		if ($this->exif_data) {
			// Check if the Exif data contains a thumbnail
			if ($thumb = exif_thumbnail($this->exif_data)) {
				// Create an image resource from the thumbnail data
				$image = imagecreatefromstring($thumb);

				// Rewind the Exif data to the beginning
				rewind($this->exif_data);

				return $image;
			}
		}

		// No thumbnail found or error occurred
		return false;
	}


	/**
	 * Check the file path and store it.
	 *
	 * @throws \InvalidArgumentException If the file path is not a PNG image.
	 *
	 * @param  string $path Location of the image in disk.
	 */
	private function checkPath(string $path): void
	{
		if ($this->isPNG($path)) {
			$this->path = $path;
		} else {
			throw new \InvalidArgumentException('The file path isn\'t PNG', 103);
		}
	}


	/**
	 * Extract metadata from a XML(XMP) as a array.
	 *
	 * @param \DOMElement|\DOMNode $node
	 * @return mixed[]|string
	 */
	private function extractNodesXML($node)
	{
		$output = [];
		switch ($node->nodeType) {
			case 1:
				for ($i = 0; $i < $node->childNodes->length; $i++) {
					$child = $node->childNodes->item($i);
					if ($child !== null) {
						$childValues = $this->extractNodesXML($child);
						// if the node has a tag name, it is not a text node
						if (isset($child->tagName)) {
							[$prefixTagName, $suffixTagName] = explode(':', $child->tagName);
							if (is_array($childValues)) {
								if (\in_array($prefixTagName, $this->tagsXMP, true)) {
									if (!isset($output[$suffixTagName])) {
										$output[$suffixTagName] = [];
									}
									$output[$suffixTagName] = $this->arrayMerge($output[$suffixTagName], $childValues);
								} else {
									$output = $this->arrayMerge($output, $childValues);
								}
							// if the node has a prefix and the prefix is in the list of prefixes, it is a XMP tag
							// if the node has a suffix and the suffix is in the list of suffixes, it is a XMP tag
							} elseif (
								\in_array($prefixTagName, $this->prefSuffXMP, true)
								|| \in_array($prefixTagName, $this->tagsXMP, true)
							) {
								if (\in_array($suffixTagName, $this->prefSuffXMP, true)) {
									$output[] = $childValues;
								} else {
									$output[$suffixTagName][] = $childValues;
								}
							// if the node has a prefix and the prefix is not in the list of prefixes, it is not a XMP tag
							} else {
								$output[$prefixTagName][$suffixTagName][] = $childValues;
							}
						// if the node does not have a tag name, it is a text node
						} elseif ($childValues || $childValues === '0') {
							$output = is_array($childValues)
								? implode(', ', $childValues)
								: (string) $childValues;
						}
					}
				}
				if (is_array($output) && $node->attributes->length) {
					$output = $this->arrayMerge($output, (array) $node->attributes);
				}
				break;
			case 4:
			case 3:
				$output = trim($node->textContent);
				break;
		}

		return $output;
	}


	/**
	 * Join the metadata keys until they reach their value.
	 *
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 *
	 * @param string $lastKey Key string from the previous array.
	 * @param string[]|null $array Value from the previous array that is a array.
	 * @return mixed[]
	 */
	private function printVertical(string $lastKey = '', ?array $array = null): array
	{
		// Create a new array to store the result.
		$columns = [];

		// If the key is not empty, add a colon to the end of the key.
		if ($lastKey !== '') {
			$lastKey .= ':';
		}

		// Traverse the array.
		foreach ($array ?: $this->metadata as $key => $value) {
			// If the value is a array, call the function recursively.
			if (is_array($value)) {
				// If the value is an indexed array, merge the key and value together.
				if (isset($value[0])) {
					$columns[] = [$lastKey . $key => implode(',', $value)];
				} else {
					// If the value is a associative array, call the function recursively.
					$columns[] = $this->printVertical($lastKey . $key, $value);
				}
			} else {
				// If the value is not an array, merge the key and value together.
				$columns[] = [$lastKey . $key => $value];
			}
		}

		// Return the merged result.
		return array_merge(...$columns);
	}


	/**
	 * Extract the data chunks more important.
	 *
	 * @see PNGMetadata::$path Location of the image in disk.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 * @throws \InvalidArgumentException If the provided argument is not a PNG image.
	 */
	private function extractChunks(): void
	{
		// Open the image file in binary mode.
		$content = fopen($this->path, 'rb');

		// Verify that the file is a PNG file.
		if (fread($content, 8) !== "\x89PNG\x0d\x0a\x1a\x0a") {
			throw new \InvalidArgumentException('Invalid PNG file signature, path "' . $this->path . '" given.', 104);
		}

		// The PNG image is composed of a series of chunks. Each chunk is composed of
		// a 4-byte length, a 4-byte type, a variable number of data bytes, and a
		// 4-byte CRC.
		$chunkHeader = fread($content, 8);
		while ($chunkHeader) {
			$chunk = unpack('Nsize/a4type', $chunkHeader);
			if ($chunk['type'] === 'IEND') {
				break;
			}
			// Read the chunk data.
			if ($chunk['type'] === 'tEXt') {
				// For the tEXt chunk, the data is a keyword and a text string.
				$this->chunks[$chunk['type']][] = explode("\0", fread($content, $chunk['size']));
				fseek($content, 4, SEEK_CUR);
			} else {
				if (
					$chunk['type'] === 'eXIf'
					|| $chunk['type'] === 'sRGB'
					|| $chunk['type'] === 'iTXt'
					|| $chunk['type'] === 'bKGD'
				) {
					// For the eXIf, sRGB, iTXt, and bKGD chunks, the data is a byte
					// sequence.
					$lastOffset = ftell($content);
					$this->chunks[$chunk['type']] = fread($content, $chunk['size']);
					fseek($content, $lastOffset, SEEK_SET);
				} elseif ($chunk['type'] === 'IHDR') {
					// For the IHDR chunk, the data is a series of fields.
					$lastOffset = ftell($content);
					for ($i = 0; $i < 6; $i++) {
						$this->chunks[$chunk['type']][] = fread($content, ($i > 1 ? 1 : 4));
					}
					fseek($content, $lastOffset, SEEK_SET);
				}
				fseek($content, $chunk['size'] + 4, SEEK_CUR);
			}
			$chunkHeader = fread($content, 8);
		}
		fclose($content);
	}


	/**
	 * Extract IHDR type from iHDR chunk as a array.
	 *
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 */
	private function extractIHDR(): void
	{
		if (isset($this->chunks['IHDR'])) {
			// Create a array that will be used to associate the chunk data with the corresponding metadata name.
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
					8 => 'ColorType',
				], [
					0 => 'Deflate/Inflate',
					8 => 'Compression',
				], [
					0 => 'Adaptive',
					8 => 'Filter',
				], [
					0 => 'Noninterlaced',
					1 => 'Adam7 Interlace',
					8 => 'Interlace',
				],
			];

			// Loop through each chunk data.
			foreach ($this->chunks['IHDR'] as $key => $value) {
				// Check if the current chunk data is not the first two bytes of the chunk.
				if ($key > 1) {
					// Check if the current chunk data is the third byte of the chunk.
					if ($key === 2) {
						// Add the metadata name (in the $ihdr array) corresponding to the chunk data and the value of the chunk data.
						$this->metadata['IHDR'][$ihdr[$key]] = ord($value);
					} else {
						// Add the metadata name (in the $ihdr array) corresponding to the chunk data and the value of the chunk data.
						$this->metadata['IHDR'][$ihdr[$key][8]] = $ihdr[$key][ord($value)];
					}
				} else {
					// Add the metadata name (in the $ihdr array) corresponding to the chunk data and the value of the chunk data.
					$this->metadata['IHDR'][$ihdr[$key]] = unpack('Ni', $value)['i'];
				}
			}
		}
	}


	/**
	 * Extract KGD type from bKGD chunk as a array.
	 *
	 * The format of bKGD chunk is:
	 *  - For indexed-color images: 1 byte.
	 *  - For grayscale images: 2 bytes.
	 *  - For truecolor images: 6 bytes.
	 *
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 */
	private function extractBKGD(): void
	{
		if (isset($this->chunks['bKGD'])) {
			// If the length of bKGD chunk is less than 2, it is an indexed-color images.
			// Otherwise it is a grayscale or truecolor images.
			$this->metadata['bKGD'] = implode(' ', unpack(strlen($this->chunks['bKGD']) < 2 ? 'C' : 'n*', $this->chunks['bKGD']));
		}
	}


	/**
	 * Extract RGB type from sRGB chunk as a array.
	 *
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 */
	private function extractRGB(): void
	{
		if (isset($this->chunks['sRGB'])) {
			// $rgb is an array with 4 possible values
			$rgb = ['Perceptual', 'Relative Colorimetric', 'Saturation', 'Absolute Colorimetric'];
			// Unpack the sRGB chunk data as a byte
			$unpacked = unpack('C', $this->chunks['sRGB']);
			// Set the sRGB value to the value of $rgb that matches the byte
			$this->metadata['sRGB'] = $rgb[end($unpacked)] ?? 'Unknown';
		}
	}


	/**
	 * Extract Exif data from eXIf chunk as a array.
	 *
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 */
	private function extractExif(): void
	{
		// Check if eXIf chunk exists.
		if (isset($this->chunks['eXIf'])) {
			// Open eXIf chunk data as a memory stream.
			$this->exif_data = fopen('php://memory', 'r+b');
			// Write eXIf chunk data to the memory stream.
			fwrite($this->exif_data, $this->chunks['eXIf']);
			// Set the pointer of memory stream to the beginning of the stream.
			rewind($this->exif_data);

			// Read Exif data from memory stream.
			$this->metadata['exif'] = array_replace(
				$this->metadata['exif'] ?? [],
				exif_read_data($this->exif_data, null, true),
			);

			// Set the pointer of memory stream to the beginning of the stream.
			rewind($this->exif_data);
		}
	}


	/**
	 * Extract Exif data form tEXt chunk as a array.
	 *
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 */
	private function extractTExif(): void
	{
		// Check if the tEXt chunk is present in the PNG file.
		if (isset($this->chunks['tEXt']) && is_array($this->chunks['tEXt'])) {

			// Loop over all tEXt chunks.
			foreach ($this->chunks['tEXt'] as $exif) {

				// Split the key into parts.
				[$group, $tag, $tag2] = array_pad(explode(':', $exif[0]), 3, null);

				// Convert the thumbnail key to uppercase.
				if ($tag === 'thumbnail') {
					$tag = strtoupper($tag);
				}

				// Store the value in the metadata array.
				$this->metadata[$group][$tag] = ($tag2 ? [$tag2 => $exif[1]] : $exif[1]);
			}
		}
	}


	/**
	 * Extract XMP data from iTXt chunk as a array.
	 *
	 * @throws \Exception If the iTXt chunck has not 'x:xmpmeta' string.
	 * @see PNGMetadata::$chunks     For the property whose chunks data are storage.
	 * @see PNGMetadata::$metadata    For the property whose metadata are storage.
	 */
	private function extractXMP(): void
	{
		if (isset($this->chunks['iTXt']) && strncmp($this->chunks['iTXt'], 'XML:com.adobe.xmp', 17) === 0) {
			// create a new DOMDocument instance
			$dom = new \DOMDocument('1.0', 'UTF-8');
			// set the preserveWhiteSpace property to false to remove white space
			$dom->preserveWhiteSpace = false;
			// set the formatOutput property to false to remove extra line breaks
			$dom->formatOutput = false;
			// set the substituteEntities property to false to prevent entity substitution
			$dom->substituteEntities = false;
			// load the XML data from the string
			$dom->loadXML(ltrim(substr($this->chunks['iTXt'], 17), "\x00"));
			// set the encoding to UTF-8
			$dom->encoding = 'UTF-8';
			// check if the root node of the XML document is of type x:xmpmeta
			if ($dom->documentElement->nodeName !== 'x:xmpmeta') {
				error_log('ExtractRoot node must be of type x:xmpmeta.');

				return;
			}
			// extract the metadata from the XML document
			if (!empty($result = $this->flatten($this->extractNodesXML($dom->documentElement)))) {
				// store the metadata in the PNGMetadata::$metadata property
				$this->metadata['xmp'] = $result;
			}
		}
	}


	/**
	 * Extract the properties with the key '0' and insert them in the first level of the array.
	 *
	 * @param mixed[] $array Matrix that contains the proprietary.
	 * @return mixed[]
	 */
	private function flatten(array $array): array
	{
		// Loop through each key in the array
		foreach ($array as $key => $value) {
			// If the value is an array
			if (is_array($value)) {
				// If the array has only one key, and that key is 0
				if (isset($value[0]) && \count($value) === 1) {
					// Set the value of this key to be the value of key 0
					$array[$key] = $value[0];
					// If the new value is an array, call flatten again to flatten it
					if (is_array($array[$key])) {
						$array[$key] = $this->flatten($array[$key]);
					}
				} else {
					// If the array doesn't have only one key, call flatten again to flatten it
					$array[$key] = $this->flatten($value);
				}
			}
		}

		return $array;
	}


	/**
	 * Merge one or more arrays more string concatenation.
	 *
	 * @param mixed[] $baseArray Array where the attributes will be inserted.
	 * @param mixed[] $array Matrix that contains the attributes.
	 * @return mixed[]
	 */
	private function arrayMerge(array $baseArray, array $array): array
	{
		// Loop through the array to be merged.
		foreach ($array as $key => $value) {
			// If the value is an object, get the value of the object.
			if (is_object($value)) {
				$value = $value->value;
			}

			// If the key already exists in the base array, merge the values.
			if (isset($baseArray[$key])) {
				// If the value is an array, merge the arrays.
				if (is_array($value)) {
					$baseArray[$key] = $this->arrayMerge($baseArray[$key], $array[$key]);
				} elseif ($baseArray[$key] !== $value) {
					// If the value is not an array, concatenate the values.
					$baseArray[$key] .= ',' . $value;
				}
			} else {
				// If the key does not exist, create the key and set the value.
				$baseArray[$key] = $value;
			}
		}

		return $baseArray;
	}
}
