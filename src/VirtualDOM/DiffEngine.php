<?php

namespace Diffyne\VirtualDOM;

/**
 * Diff engine that compares two Virtual DOM trees and generates patches.
 */
class DiffEngine
{
    /**
     * Patch type constants.
     */
    public const PATCH_CREATE = 'create';

    public const PATCH_REMOVE = 'remove';

    public const PATCH_REPLACE = 'replace';

    public const PATCH_UPDATE_TEXT = 'update_text';

    public const PATCH_UPDATE_ATTRS = 'update_attrs';

    public const PATCH_REORDER = 'reorder';

    /**
     * Generated patches.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $patches = [];

    /**
     * Compare two VNode trees and generate patches.
     *
     * @param array<int, int> $path
     * @return array<int, array<string, mixed>>
     */
    public function diff(?VNode $oldNode, ?VNode $newNode, array $path = []): array
    {
        $this->patches = [];

        $this->diffNodes($oldNode, $newNode, $path);

        return $this->patches;
    }

    /**
     * Diff two nodes recursively.
     *
     * @param array<int, int> $path
     */
    protected function diffNodes(?VNode $oldNode, ?VNode $newNode, array $path): void
    {
        // Case 1: New node created
        if ($oldNode === null && $newNode !== null) {
            $this->addPatch(self::PATCH_CREATE, $path, [
                'node' => $newNode->toMinimal(),
            ]);

            return;
        }

        // Case 2: Node removed
        if ($oldNode !== null && $newNode === null) {
            $this->addPatch(self::PATCH_REMOVE, $path);

            return;
        }

        // Case 3: Both nodes are null
        if ($oldNode === null && $newNode === null) {
            return;
        }

        // Case 4: Node type changed
        if ($oldNode->type !== $newNode->type) {
            $this->addPatch(self::PATCH_REPLACE, $path, [
                'node' => $newNode->toMinimal(),
            ]);

            return;
        }

        // Case 5: Element tag changed
        if ($oldNode->isElement() && $newNode->isElement() && $oldNode->tag !== $newNode->tag) {
            $this->addPatch(self::PATCH_REPLACE, $path, [
                'node' => $newNode->toMinimal(),
            ]);

            return;
        }

        // Case 6: Text content changed
        if ($oldNode->isText() && $newNode->isText() && $oldNode->text !== $newNode->text) {
            $this->addPatch(self::PATCH_UPDATE_TEXT, $path, [
                'text' => $newNode->text,
            ]);

            return;
        }

        // Case 7: Element attributes changed
        if ($oldNode->isElement() && $newNode->isElement()) {
            $attrChanges = $this->diffAttributes($oldNode->attributes, $newNode->attributes);

            if (! empty($attrChanges)) {
                $this->addPatch(self::PATCH_UPDATE_ATTRS, $path, $attrChanges);
            }

            // Diff children
            $this->diffChildren($oldNode->children, $newNode->children, $path);
        }
    }

    /**
     * Diff attributes between two nodes.
     *
     * @param array<string, mixed> $oldAttrs
     * @param array<string, mixed> $newAttrs
     * @return array<string, mixed>
     */
    protected function diffAttributes(array $oldAttrs, array $newAttrs): array
    {
        $changes = [
            'set' => [],
            'remove' => [],
        ];

        // Find attributes to set or update
        foreach ($newAttrs as $key => $value) {
            if (! isset($oldAttrs[$key]) || $oldAttrs[$key] !== $value) {
                $changes['set'][$key] = $value;
            }
        }

        // Find attributes to remove
        foreach ($oldAttrs as $key => $value) {
            if (! isset($newAttrs[$key])) {
                $changes['remove'][] = $key;
            }
        }

        // Return null if no changes
        if (empty($changes['set']) && empty($changes['remove'])) {
            return [];
        }

        return $changes;
    }

    /**
     * Diff children arrays.
     *
     * @param array<int, VNode> $oldChildren
     * @param array<int, VNode> $newChildren
     * @param array<int, int> $parentPath
     */
    protected function diffChildren(array $oldChildren, array $newChildren, array $parentPath): void
    {
        $oldCount = count($oldChildren);
        $newCount = count($newChildren);

        // Check if we can use keyed diffing
        $oldKeyed = $this->extractKeyedNodes($oldChildren);
        $newKeyed = $this->extractKeyedNodes($newChildren);

        // Use keyed diffing if at least one list has keys (even if the other is empty)
        if (! empty($oldKeyed) || ! empty($newKeyed)) {
            // If old list has keys but new doesn't (or vice versa), still use keyed diffing
            // This handles cases where list goes from keyed to empty or vice versa
            $this->diffKeyedChildren($oldChildren, $newChildren, $parentPath, $oldKeyed, $newKeyed);

            return;
        }

        // Simple index-based diffing (no keys)
        $maxCount = max($oldCount, $newCount);
        
        // First, remove excess old children (in reverse order to avoid index shifting)
        if ($oldCount > $newCount) {
            for ($i = $oldCount - 1; $i >= $newCount; $i--) {
                $this->diffNodes($oldChildren[$i], null, [...$parentPath, $i]);
            }
        }

        // Then diff/create/update remaining children
        for ($i = 0; $i < $newCount; $i++) {
            $oldChild = $oldChildren[$i] ?? null;
            $newChild = $newChildren[$i] ?? null;
            $childPath = [...$parentPath, $i];

            $this->diffNodes($oldChild, $newChild, $childPath);
        }
    }

    /**
     * Diff children using keys for efficient reordering.
     *
     * @param array<int, VNode> $oldChildren
     * @param array<int, VNode> $newChildren
     * @param array<int, int> $parentPath
     * @param array<string, int> $oldKeyed
     * @param array<string, int> $newKeyed
     */
    protected function diffKeyedChildren(
        array $oldChildren,
        array $newChildren,
        array $parentPath,
        array $oldKeyed,
        array $newKeyed
    ): void {
        // First, identify nodes to remove
        $toRemove = [];
        foreach ($oldKeyed as $key => $position) {
            if (! isset($newKeyed[$key])) {
                $toRemove[] = $position;
            }
        }

        // Remove in reverse order to avoid index shifting
        rsort($toRemove);
        foreach ($toRemove as $position) {
            $this->diffNodes($oldChildren[$position], null, [...$parentPath, $position]);
        }

        // Process all new children in order
        foreach ($newChildren as $newIndex => $newChild) {
            $key = $newChild->key;
            $oldChildAtIndex = $oldChildren[$newIndex] ?? null;

            if ($key !== null && isset($oldKeyed[$key])) {
                // Node exists in old list
                $oldPosition = $oldKeyed[$key];

                // Check if the key at this position changed (reorder)
                if ($oldChildAtIndex && $oldChildAtIndex->key !== $key) {
                    // Position has different key - replace the node
                    $this->addPatch(self::PATCH_REPLACE, [...$parentPath, $newIndex], [
                        'node' => $newChild->toMinimal(),
                    ]);
                } else {
                    // Same position or no old child - just diff content
                    $this->diffNodes(
                        $oldChildren[$oldPosition],
                        $newChild,
                        [...$parentPath, $newIndex]
                    );
                }
            } else {
                // New node, create it
                $this->diffNodes(null, $newChild, [...$parentPath, $newIndex]);
            }
        }
    }

    /**
     * Extract keyed nodes from children array.
     *
     * @param array<int, VNode> $children
     * @return array<string, int>
     */
    protected function extractKeyedNodes(array $children): array
    {
        $keyed = [];

        foreach ($children as $index => $child) {
            if ($child->key !== null) {
                $keyed[$child->key] = $index;
            }
        }

        return $keyed;
    }

    /**
     * Add a patch to the collection.
     *
     * @param array<int, int> $path
     * @param array<string, mixed> $data
     */
    protected function addPatch(string $type, array $path, array $data = []): void
    {
        $this->patches[] = [
            'type' => $type,
            'path' => $path,
            'data' => $data,
        ];
    }

    /**
     * Get all generated patches.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPatches(): array
    {
        return $this->patches;
    }

    /**
     * Optimize patches by removing redundant operations.
     *
     * @param array<int, array<string, mixed>> $patches
     * @return array<int, array<string, mixed>>
     */
    public function optimizePatches(array $patches): array
    {
        // Remove patches for nodes that were replaced at a parent level
        $replacedPaths = [];

        foreach ($patches as $patch) {
            if ($patch['type'] === self::PATCH_REPLACE || $patch['type'] === self::PATCH_REMOVE) {
                $replacedPaths[] = $patch['path'];
            }
        }

        return array_filter($patches, function ($patch) use ($replacedPaths) {
            foreach ($replacedPaths as $replacedPath) {
                if ($this->isDescendantPath($patch['path'], $replacedPath)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Check if a path is a descendant of another path.
     *
     * @param array<int, int> $childPath
     * @param array<int, int> $parentPath
     */
    protected function isDescendantPath(array $childPath, array $parentPath): bool
    {
        if (count($childPath) <= count($parentPath)) {
            return false;
        }

        for ($i = 0; $i < count($parentPath); $i++) {
            if ($childPath[$i] !== $parentPath[$i]) {
                return false;
            }
        }

        return true;
    }
}
