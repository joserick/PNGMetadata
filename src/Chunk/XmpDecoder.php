<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use DOMDocument;
use PNGMetadata\Exception\ChunkDecodingException;

/**
 * Parses an XMP packet (XML) into a structured array.
 *
 * Malformed XML is a decode failure governed by the unified policy (strict
 * mode reports it; lenient mode omits the packet without aborting the rest
 * of the extraction). A well-formed packet with no extractable properties
 * returns null - an empty XMP document is not a violation.
 */
final class XmpDecoder
{
	/**
	 * @return array<string, mixed>|null Structured XMP properties, or null when the
	 *                                   packet is valid but carries nothing.
	 *
	 * @throws ChunkDecodingException When the packet is not well-formed XML.
	 */
	public static function parse(string $xml, string $code, int $offset): ?array
	{
		if (\trim($xml) === '') {
			return null;
		}

		$dom = new DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = false;

		$previous = \libxml_use_internal_errors(true);
		$loaded = $dom->loadXML($xml);
		\libxml_clear_errors();
		\libxml_use_internal_errors($previous);

		if (!$loaded || $dom->documentElement === null) {
			throw ChunkDecodingException::forChunk($code, $offset, 'XMP packet is not well-formed XML.');
		}

		$namespaces = [];
		foreach (self::collectNamespaces($dom) as $prefix => $uri) {
			if ($prefix === 'xml' || $prefix === 'xmlns' || $prefix === 'rdf' || $prefix === 'x') {
				continue;
			}
			$namespaces[$prefix] = true;
		}

		$result = [];
		foreach ($dom->getElementsByTagNameNS('*', '*') as $element) {
			$prefix = $element->prefix !== '' ? $element->prefix : 'default';
			if (!isset($namespaces[$prefix])) {
				continue;
			}
			$value = \trim($element->textContent);
			if ($value === '' && $element->childElementCount > 0) {
				continue;
			}
			$result[$prefix][$element->localName] = $value;
		}

		return $result === [] ? null : $result;
	}

	/**
	 * @return array<string, string> prefix => namespace URI
	 */
	private static function collectNamespaces(DOMDocument $dom): array
	{
		$namespaces = [];
		$xpath = new \DOMXPath($dom);
		foreach ($xpath->query('//namespace::*') ?: [] as $node) {
			if ($node instanceof \DOMNameSpaceNode && \is_string($node->nodeValue)) {
				$namespaces[$node->prefix] = $node->nodeValue;
			}
		}

		return $namespaces;
	}
}
