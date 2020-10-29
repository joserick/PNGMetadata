<?php

declare(strict_types=1);

namespace PNGMetadata;


use ArrayObject;
use Exception;

/**
 * PNG Metadata.
 *
 * @author <joserick.92@gmail.com> José Erick Carreón Gómez
 * @copyright (c) 2019 José Erick Carreón Gómez
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public Licence (GPLv3)
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
 * PNG Metadata class for extraction of XMP, TEXIF, EXIF, BKGD, RBG and IHDR.
 *
 * Returns the complete information found in the different types
 * of metadata within a PNG format image.
 */
class PNGMetadata extends ArrayObject
{

	/** @var mixed[] */
	private array $metadata = [];

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


	public function __construct(?string $path = null)
	{
		$this->extractChunks($path);
		$this->extractXMP();
		$this->extractTExif();
		$this->extractExif();
		$this->extractBKGD();
		$this->extractRBG();
		$this->extractIHDR();
		ksort($this->metadata);

		parent:: __construct($this->metadata);
	}


	/** In case of an error in the extraction of metadata this will return null. */
	public static function extract(?string $path = null): ?self
	{
		try {
			return new self($path);
		} catch (Exception $e) {
			return null;
		}
	}


	/**
	 * @return mixed[]
	 */
	public function toArray(): array
	{
		return $this->metadata;
	}


	/**
	 * Return a metadata specific.
	 *
	 * @param string $string A string with structure, e.g. 'exif:THUMBNAIL:Compression'.
	 * @return string|mixed[]|null
	 */
	public function get(string $string)
	{
		if (is_string($string)) {
			$array_value = &$this->metadata;
			$keys = explode(':', $string);
			foreach ($keys as $key) {
				if (isset($array_value[$key])) {
					$array_value = &$array_value[$key];
				} else {
					return null;
				}
			}

			return $array_value;
		}

		return null;
	}


	/** Return the metadata with a string structura of two colums. */
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
	 * @return mixed[]
	 * @see PNGMetadata::extractRDF() To read a xml looking for metadata.
	 */
	public function extractNodesXML(\DOMElement $node): array
	{
		$output = [];
		switch ($node->nodeType) {
			case 1:
				for ($i = 0; $i < $node->childNodes->length; $i++) {
					$child = $node->childNodes->item($i);
					$childValues = $this->extractNodesXML($child);
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
						} elseif (\in_array($prefixTagName, $this->prefSuffXMP, true) || \in_array($prefixTagName, $this->tagsXMP, true)) {
							if (\in_array($suffixTagName, $this->prefSuffXMP, true)) {
								$output[] = $childValues;
							} else {
								$output[$suffixTagName][] = $childValues;
							}
						} else {
							$output[$prefixTagName][$suffixTagName][] = $childValues;
						}
					} elseif ($childValues || $childValues === '0') {
						$output = ( string ) $childValues;
					}
				}
				if (is_array($output) && $node->attributes->length) {
					$output = $this->arrayMerge($output, $node->attributes);
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
	 * @param string last_key Key string from the previous array.
	 * @param string[] $array Value from the previous array that is a array.
	 * @return mixed[]
	 */
	private function printVertical(string $lastKey = '', ?array $array = null): array
	{
		$columns = [];
		if ($lastKey !== '') {
			$lastKey .= ':';
		}
		foreach ($array ?: $this->metadata as $key => $value) {
			if (is_array($value)) {
				if (isset($value[0])) {
					$columns[] = [$lastKey . $key => implode(',', $value)];
				} else {
					$columns[] = $this->printVertical($lastKey . $key, $value);
				}
			} else {
				$columns[] = [$lastKey . $key => $value];
			}
		}

		return array_merge(...$columns);
	}


	private function extractChunks(string $path): void
	{
		$content = fopen($path, 'rb');
		if ("\x89PNG\x0d\x0a\x1a\x0a" !== fread($content, 8)) {
			throw new \InvalidArgumentException('Invalid PNG file signature, path "' . $path . '" given.');
		}

		$chunkHeader = fread($content, 8);
		while ($chunkHeader) {
			$chunk = unpack('Nsize/a4type', $chunkHeader);
			if ($chunk['type'] === 'IEND') {
				break;
			}
			if ($chunk['type'] === 'tEXt') {
				$this->chunks[$chunk['type']][] = explode("\0", fread($content, $chunk['size']));
				fseek($content, 4, SEEK_CUR);
			} else {
				if ($chunk['type'] === 'eXIf' || $chunk['type'] === 'sRGB' || $chunk['type'] === 'iTXt' || $chunk['type'] === 'bKGD') {
					$lastOffset = ftell($content);
					$this->chunks[$chunk['type']] = fread($content, $chunk['size']);
					fseek($content, $lastOffset, SEEK_SET);
				} elseif ($chunk['type'] === 'IHDR') {
					$lastOffset = ftell($content);
					for ($i = 0; $i < 6; $i++) {
						$this->chunks[$chunk['type']][] = fread($content, (($i > 1) ? 1 : 4));
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
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 */
	private function extractIHDR(): void
	{
		if (isset($this->chunks['IHDR'])) {
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

			foreach ($this->chunks['IHDR'] as $key => $value) {
				if ($key > 1) {
					if ($key === 2) {
						$this->metadata['IHDR'][$ihdr[$key]] = ord($value);
					} else {
						$this->metadata['IHDR'][$ihdr[$key][8]] = $ihdr[$key][ord($value)];
					}
				} else {
					$this->metadata['IHDR'][$ihdr[$key]] = unpack('Ni', $value)['i'];
				}
			}
		}
	}


	/**
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 */
	private function extractBKGD(): void
	{
		if (isset($this->chunks['bKGD'])) {
			$this->metadata['bKGD'] = join(" ", unpack(strlen($this->chunks['bKGD']) < 2 ? 'C' : 'n*', $this->chunks['bKGD']));
		}
	}


	/**
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 */
	private function extractRBG(): void
	{
		if (isset($this->chunks['sRGB'])) {
			$rbg = ['Perceptual', 'Relative Colorimetric', 'Saturation', 'Absolute Colorimetric'];
			$this->metadata['sRBG'] = $rbg[end(... [unpack('C', $this->chunks['sRGB'])])];
		}
	}


	/**
	 * @see PNGMetadata::$metadata For the property whose metadata are storage.
	 * @see PNGMetadata::$chunks For the property whose chunks data are storage.
	 */
	private function extractExif(): void
	{
		if (isset($this->chunks['eXIf'])) {
			$this->metadata['exif'] = array_replace(
				$this->metadata['exif'],
				exif_read_data('data://image/jpeg;base64,' . base64_encode($this->chunks['eXIf']))
			);
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
		if (isset($this->chunks['tEXt']) && is_array($this->chunks['tEXt'])) {
			foreach ($this->chunks['tEXt'] as $exif) {
				[$group, $tag, $tag2] = array_pad(explode(':', $exif[0]), 3, null);
				if ($tag === 'thumbnail') {
					$tag = strtoupper($tag);
				}
				$this->metadata[$group][$tag] = (($tag2) ? [$tag2 => $exif[1]] : $exif[1]);
			}
		}
	}


	/**
	 * @throws \Exception If the iTXt chunck has not 'x:xmpmeta' string.
	 * @see PNGMetadata::extractRDF() To read a xml looking for metadata.
	 * @see PNGMetadata::$chunks     For the property whose chunks data are storage.
	 * @see PNGMetadata::$metadata    For the property whose metadata are storage.
	 */
	private function extractXMP(): void
	{
		if (isset($this->chunks['iTXt']) && strncmp($this->chunks['iTXt'], 'XML:com.adobe.xmp', 17) === 0) {
			$dom = new \DomDocument('1.0', 'UTF-8');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = false;
			$dom->substituteEntities = false;
			$dom->loadXML(ltrim(substr($this->chunks['iTXt'], 17), "\x00"));
			$dom->encoding = 'UTF-8';
			if ('x:xmpmeta' !== $dom->documentElement->nodeName) {
				error_log('ExtractRoot node must be of type x:xmpmeta.');

				return;
			}
			if (!empty($result = $this->flatten($this->extractNodesXML($dom->documentElement)))) {
				$this->metadata['xmp'] = $result;
			}
		}
	}


	/**
	 * @param mixed[] $array
	 * @return mixed[]
	 */
	private function flatten(array $array): array
	{
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				if (isset($value[0]) && \count($value) === 1) {
					$array[$key] = $value[0];
					if (is_array($array[$key])) {
						$array[$key] = $this->flatten($array[$key]);
					}
				} else {
					$array[$key] = $this->flatten($value);
				}
			}
		}

		return $array;
	}


	/**
	 * @param mixed[] $baseArray Array where the attributes will be inserted.
	 * @param mixed[] $array Matrix that contains the attributes.
	 * @return mixed[]
	 */
	private function arrayMerge(array $baseArray, array $array): array
	{
		foreach ($array as $key => $value) {
			if (is_object($value)) {
				$value = $value->value;
			}
			if (isset($baseArray[$key])) {
				if (is_array($value)) {
					$baseArray[$key] = $this->arrayMerge($baseArray[$key], $array[$key]);
				} elseif ($baseArray[$key] !== $value) {
					$baseArray[$key] .= ',' . $value;
				}
			} else {
				$baseArray[$key] = $value;
			}
		}

		return $baseArray;
	}
}
