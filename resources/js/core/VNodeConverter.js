/**
 * VNodeConverter.js
 * Converts Virtual DOM nodes to actual DOM nodes (Single Responsibility)
 */

export class VNodeConverter {
    /**
     * Check if a tag name is an SVG element
     */
    isSVGElement(tagName) {
        const svgTags = new Set([
            'svg', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse',
            'g', 'defs', 'use', 'text', 'tspan', 'image', 'foreignobject',
            'clippath', 'mask', 'pattern', 'lineargradient', 'radialgradient', 'stop',
            'animate', 'animatetransform', 'animatemotion', 'title', 'desc', 'metadata'
        ]);
        return svgTags.has(tagName.toLowerCase());
    }

    /**
     * Normalize SVG attribute names to their correct case
     * SVG attributes are case-sensitive (e.g., viewBox not viewbox)
     */
    normalizeSVGAttributeName(key) {
        const svgAttributeMap = {
            'viewbox': 'viewBox',
            'preserveaspectratio': 'preserveAspectRatio',
            'gradientunits': 'gradientUnits',
            'gradienttransform': 'gradientTransform',
            'xlink:href': 'xlink:href',
            'xlink:title': 'xlink:title',
            'stroke-linecap': 'stroke-linecap',
            'stroke-linejoin': 'stroke-linejoin',
            'stroke-width': 'stroke-width',
            'stroke-dasharray': 'stroke-dasharray',
            'stroke-dashoffset': 'stroke-dashoffset',
            'fill-rule': 'fill-rule',
            'clip-path': 'clip-path',
            'clip-rule': 'clip-rule',
            'text-anchor': 'text-anchor',
            'dominant-baseline': 'dominant-baseline',
            'baseline-shift': 'baseline-shift',
            'alignment-baseline': 'alignment-baseline',
            'font-family': 'font-family',
            'font-size': 'font-size',
            'font-weight': 'font-weight',
            'font-style': 'font-style',
            'text-decoration': 'text-decoration',
            'letter-spacing': 'letter-spacing',
            'word-spacing': 'word-spacing',
            'text-transform': 'text-transform',
            'writing-mode': 'writing-mode',
            'glyph-orientation-vertical': 'glyph-orientation-vertical',
            'glyph-orientation-horizontal': 'glyph-orientation-horizontal',
        };
        
        const lowerKey = key.toLowerCase();
        return svgAttributeMap[lowerKey] || key;
    }

    /**
     * Convert VNode to actual DOM node
     */
    vnodeToDOM(vnode, parentNamespace = null) {
        // Text node (minified)
        if (vnode.x !== undefined) {
            return document.createTextNode(vnode.x);
        }

        // Comment node (minified)
        if (vnode.m !== undefined) {
            return document.createComment(vnode.m);
        }

        // Element node (minified)
        if (vnode.t) {
            const tagName = vnode.t.toLowerCase();
            const isSVG = this.isSVGElement(tagName);
            const namespace = isSVG ? 'http://www.w3.org/2000/svg' : parentNamespace;

            // createElementNS requires lowercase tag names
            const element = namespace
                ? document.createElementNS(namespace, tagName)
                : document.createElement(tagName);

            const attrs = vnode.a || vnode.attributes || {};
            Object.entries(attrs).forEach(([key, value]) => {
                if (namespace) {
                    // Normalize SVG attribute names to correct case (e.g., viewBox not viewbox)
                    const normalizedKey = this.normalizeSVGAttributeName(key);
                    // For SVG elements, use setAttributeNS with null namespace
                    // This ensures proper attribute handling for SVG
                    element.setAttributeNS(null, normalizedKey, String(value));
                } else {
                    element.setAttribute(key, String(value));
                }
            });

            const children = vnode.c || vnode.children || [];
            const childNamespace = isSVG ? 'http://www.w3.org/2000/svg' : namespace;
            children.forEach(child => {
                element.appendChild(this.vnodeToDOM(child, childNamespace));
            });

            return element;
        }

        // Legacy format fallback
        if (vnode.type === 'text') {
            return document.createTextNode(vnode.text);
        }

        if (vnode.type === 'element') {
            const tagName = vnode.tag.toLowerCase();
            const isSVG = this.isSVGElement(tagName);
            const namespace = isSVG ? 'http://www.w3.org/2000/svg' : parentNamespace;

            // createElementNS requires lowercase tag names
            const element = namespace
                ? document.createElementNS(namespace, tagName)
                : document.createElement(tagName);

            Object.entries(vnode.attributes || {}).forEach(([key, value]) => {
                if (namespace) {
                    // Normalize SVG attribute names to correct case (e.g., viewBox not viewbox)
                    const normalizedKey = this.normalizeSVGAttributeName(key);
                    // For SVG elements, use setAttributeNS with null namespace
                    // This ensures proper attribute handling for SVG
                    element.setAttributeNS(null, normalizedKey, String(value));
                } else {
                    element.setAttribute(key, String(value));
                }
            });

            const childNamespace = isSVG ? 'http://www.w3.org/2000/svg' : namespace;
            (vnode.children || []).forEach(child => {
                element.appendChild(this.vnodeToDOM(child, childNamespace));
            });

            return element;
        }

        return document.createTextNode('');
    }

    /**
     * Build simplified VDOM from actual DOM
     */
    buildVDOM(element) {
        return {
            tag: element.tagName.toLowerCase(),
            attrs: this.getAttributes(element),
            children: Array.from(element.childNodes).map(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    return { type: 'text', value: node.textContent };
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    return this.buildVDOM(node);
                }
                return null;
            }).filter(Boolean)
        };
    }

    /**
     * Get all attributes from an element
     */
    getAttributes(element) {
        const attrs = {};
        Array.from(element.attributes).forEach(attr => {
            attrs[attr.name] = attr.value;
        });
        return attrs;
    }
}
