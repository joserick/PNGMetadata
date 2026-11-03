<?php

declare(strict_types=1);

namespace PNGMetadata\Chunk;

use DOMDocument;

/**
 * Parses an XMP packet (XML) into a structured array.
 *
 * Invalid XML yields null so a broken packet never aborts extraction
 * of the remaining metadata.
 */
final class XmpDecoder
{
	/**
	 * @return array<string, mixed>|null Structured XMP properties, or null on invalid XML.
	 */
	public static function parse(string $xml): ?array
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
			return null;
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
