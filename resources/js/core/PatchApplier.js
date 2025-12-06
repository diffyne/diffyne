/**
 * PatchApplier.js
 * Applies Virtual DOM patches to actual DOM (Single Responsibility)
 */

import { VNodeConverter } from './VNodeConverter.js';

export class PatchApplier {
    constructor() {
        this.converter = new VNodeConverter();
    }

    /**
     * Apply all patches to a component
     */
    applyPatches(contentRoot, patches) {
        if (!contentRoot) {
            console.error('[PatchApplier] Content root is null, cannot apply patches');
            return;
        }

        if (!patches || patches.length === 0) {
            return;
        }

        // Check for full replacement
        if (this.isFullReplacement(patches)) {
            return this.replaceContent(contentRoot, patches[0]);
        }

        // Apply individual patches in correct order:
        // 1. Removes first (in reverse index order to avoid shifting)
        // 2. Then creates/updates/replaces
        const removePatches = [];
        const otherPatches = [];

        patches.forEach(patch => {
            const type = this.expandType(patch.t || patch.type);
            if (type === 'remove') {
                removePatches.push(patch);
            } else {
                otherPatches.push(patch);
            }
        });

        // Sort removes by path in reverse order (highest index first)
        // This prevents index shifting when removing multiple items
        removePatches.sort((a, b) => {
            const pathA = a.p || a.path || [];
            const pathB = b.p || b.path || [];
            
            // Compare paths from end to start (reverse order)
            const minLen = Math.min(pathA.length, pathB.length);
            for (let i = minLen - 1; i >= 0; i--) {
                if (pathA[i] !== pathB[i]) {
                    return pathB[i] - pathA[i]; // Reverse order (higher index first)
                }
            }
            return pathB.length - pathA.length;
        });

        // Sort other patches by depth (deepest first) for creates/updates
        otherPatches.sort((a, b) => {
            const pathA = a.p || a.path || [];
            const pathB = b.p || b.path || [];
            return pathB.length - pathA.length;
        });

        try {
            // Apply removes first (in reverse order)
            removePatches.forEach((patch, index) => {
                try {
                    this.applyPatch(contentRoot, patch);
                } catch (error) {
                    console.error(`[PatchApplier] Failed to apply remove patch ${index}:`, patch, error);
                    throw error;
                }
            });

            // Then apply creates/updates/replaces
            otherPatches.forEach((patch, index) => {
                try {
                    this.applyPatch(contentRoot, patch);
                } catch (error) {
                    console.error(`[PatchApplier] Failed to apply patch ${index}:`, patch, error);
                    throw error;
                }
            });
        } catch (error) {
            console.error('[PatchApplier] Patch application failed:', error);
            throw error;
        }
    }

    /**
     * Check if patches represent a full content replacement
     */
    isFullReplacement(patches) {
        if (patches.length !== 1) return false;
        
        const patch = patches[0];
        const type = patch.t || patch.type;
        const path = patch.p || patch.path;
        
        return (type === 'c' || type === 'create') && 
               (path?.length === 0 || !path);
    }

    /**
     * Replace entire content
     */
    replaceContent(contentRoot, patch) {
        const data = patch.d || patch.data;
        const newContent = this.converter.vnodeToDOM(data.node);
        contentRoot.parentNode.replaceChild(newContent, contentRoot);
        return true;
    }

    /**
     * Apply a single patch
     */
    applyPatch(root, patch) {
        const type = this.expandType(patch.t || patch.type);
        const path = patch.p || patch.path || [];
        const data = patch.d || patch.data;
        
        let target, insertIndex;
        
        if (type === 'create' && path.length > 0) {
            const parentPath = path.slice(0, -1);
            insertIndex = path[path.length - 1];
            target = this.getNodeByPath(root, parentPath);
        } else {
            target = this.getNodeByPath(root, path);
            insertIndex = null;
        }

        if (!target) {
            console.warn(`[PatchApplier] Target node not found for patch type ${type} at path ${path.join('.')}`);
            return;
        }

        switch (type) {
            case 'create':
                this.patchCreate(target, data, insertIndex);
                break;
            case 'remove':
                this.patchRemove(target);
                break;
            case 'replace':
                this.patchReplace(target, data);
                break;
            case 'update_text':
                this.patchUpdateText(target, data);
                break;
            case 'update_attrs':
                this.patchUpdateAttrs(target, data);
                break;
            case 'reorder':
                this.patchReorder(target, data);
                break;
        }
    }

    /**
     * Expand minified patch type
     */
    expandType(type) {
        const typeMap = {
            'c': 'create',
            'r': 'remove',
            'R': 'replace',
            't': 'update_text',
            'a': 'update_attrs',
            'o': 'reorder'
        };
        return typeMap[type] || type;
    }

    /**
     * Get DOM node by path
     */
    getNodeByPath(root, path) {
        if (!path || path.length === 0) {
            return root;
        }

        let node = root;

        for (const index of path) {
            if (!node) {
                console.warn(`[PatchApplier] Node not found at path ${path.join('.')}, index ${index}`);
                return null;
            }
            
            const meaningfulChildren = Array.from(node.childNodes).filter(child => {
                if (child.nodeType === Node.TEXT_NODE) {
                    return child.textContent.trim() !== '';
                }
                return true;
            });
            
            if (index < 0 || index >= meaningfulChildren.length) {
                console.warn(`[PatchApplier] Index ${index} out of bounds (${meaningfulChildren.length} children) at path ${path.join('.')}`);
                return null;
            }
            
            node = meaningfulChildren[index];
        }

        return node;
    }

    /**
     * Create new node
     */
    patchCreate(parent, data, insertIndex = null) {
        const newNode = this.converter.vnodeToDOM(data.node);
        
        if (insertIndex !== null) {
            const meaningfulChildren = Array.from(parent.childNodes).filter(child => {
                if (child.nodeType === Node.TEXT_NODE) {
                    return child.textContent.trim() !== '';
                }
                return true;
            });
            
            const referenceNode = meaningfulChildren[insertIndex];
            if (referenceNode) {
                parent.insertBefore(newNode, referenceNode);
            } else {
                parent.appendChild(newNode);
            }
        } else {
            parent.appendChild(newNode);
        }
    }

    /**
     * Remove node
     */
    patchRemove(node) {
        if (!node || !node.parentNode) {
            console.warn('[PatchApplier] Cannot remove node - node or parent is null');
            return;
        }
        
        try {
            node.parentNode.removeChild(node);
        } catch (error) {
            console.error('[PatchApplier] Failed to remove node:', error, node);
            // Don't throw - continue with other patches
        }
    }

    /**
     * Replace node
     */
    patchReplace(oldNode, data) {
        const newNode = this.converter.vnodeToDOM(data.node);
        oldNode.parentNode?.replaceChild(newNode, oldNode);
    }

    /**
     * Update text content
     */
    patchUpdateText(node, data) {
        if (node.nodeType === Node.TEXT_NODE) {
            node.textContent = data.x || data.text;
        }
    }

    /**
     * Update attributes
     */
    patchUpdateAttrs(element, data) {
        const setAttrs = data.s || data.set || {};
        const removeAttrs = data.r || data.remove || [];
        
        Object.entries(setAttrs).forEach(([key, value]) => {
            element.setAttribute(key, value);
            
            if ((element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || 
                 element.tagName === 'SELECT') && key === 'value') {
                element.value = value;
            }
        });

        removeAttrs.forEach(key => {
            element.removeAttribute(key);
            
            if ((element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || 
                 element.tagName === 'SELECT') && key === 'value') {
                element.value = '';
            }
        });
    }

    /**
     * Reorder children
     */
    patchReorder(parent, data) {
        data.moves.forEach(move => {
            const node = parent.childNodes[move.from];
            if (node) {
                parent.insertBefore(node, parent.childNodes[move.to]);
            }
        });
    }
}
