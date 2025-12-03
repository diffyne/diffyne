<?php

namespace Diffyne\VirtualDOM;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Parser to convert HTML strings to Virtual DOM nodes.
 */
class HTMLParser
{
    /**
     * Parse HTML string to VNode tree.
     */
    public function parse(string $html): VNode
    {
        // Wrap in a root element to handle fragments
        $html = '<div>'.$html.'</div>';

        $dom = new DOMDocument('1.0', 'UTF-8');

        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        $root = $this->convertDOMNode($dom->documentElement);

        // Unwrap the root div we added
        if ($root->isElement() && $root->tag === 'div' && count($root->children) === 1) {
            return $root->children[0];
        }

        return $root;
    }

    /**
     * Convert a DOMNode to a VNode.
     */
    protected function convertDOMNode(DOMNode $node): VNode
    {
        if ($node instanceof DOMText) {
            return VNode::text($node->textContent);
        }

        if ($node instanceof DOMComment) {
            return VNode::comment($node->textContent);
        }

        if ($node instanceof DOMElement) {
            $attributes = $this->extractAttributes($node);
            $children = $this->convertChildren($node);

            $vnode = VNode::element($node->nodeName, $attributes, $children);
            
            if (isset($attributes['diff:key'])) {
                $vnode->key = $attributes['diff:key'];
            } elseif (isset($attributes['key'])) {
                $vnode->key = $attributes['key'];
            }
            
            return $vnode;
        }

        // Default to text node for unknown types
        return VNode::text('');
    }

    /**
     * Extract attributes from a DOM element.
     */
    protected function extractAttributes(DOMElement $element): array
    {
        $attributes = [];

        if ($element->hasAttributes()) {
            /** @var \DOMAttr $attr */
            foreach ($element->attributes as $attr) {
                $attributes[$attr->name] = $attr->value;
            }
        }

        return $attributes;
    }

    /**
     * Convert child nodes to VNode array.
     */
    protected function convertChildren(DOMElement $element): array
    {
        $children = [];

        foreach ($element->childNodes as $child) {
            $vnode = $this->convertDOMNode($child);

            // Skip empty text nodes (whitespace only)
            if ($vnode->isText() && trim($vnode->text) === '') {
                continue;
            }

            $children[] = $vnode;
        }

        return $children;
    }

    /**
     * Parse multiple HTML fragments.
     */
    public function parseFragments(array $htmlStrings): array
    {
        return array_map(fn ($html) => $this->parse($html), $htmlStrings);
    }
}
