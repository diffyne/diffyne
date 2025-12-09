<?php

namespace Diffyne\VirtualDOM;

use Diffyne\Component;
use Diffyne\Security\StateSigner;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use ReflectionClass;

/**
 * Renderer for Diffyne components that generates Virtual DOM and patches.
 */
class Renderer
{
    protected HTMLParser $parser;

    protected DiffEngine $diffEngine;

    /**
     * Component Virtual DOM snapshots.
     *
     * @var array<string, VNode>
     */
    protected array $snapshots = [];

    public function __construct()
    {
        $this->parser = new HTMLParser();
        $this->diffEngine = new DiffEngine();
    }

    /**
     * Render a component and return initial HTML with metadata.
     *
     * @return array<string, mixed>
     */
    public function renderInitial(Component $component): array
    {
        $html = $this->renderComponentView($component);
        $vdom = $this->parser->parse($html);

        // Store snapshot for future diffs
        $this->snapshots[$component->id] = $vdom;

        $state = $component->getState();

        // Normalize state for signature generation (convert empty strings to null to match request format)
        $normalizedState = $this->normalizeStateForSigning($state);

        return [
            'id' => $component->id,
            'html' => $html,
            'state' => $state,
            'fingerprint' => $component->calculateFingerprint(),
            'signature' => StateSigner::sign($normalizedState, $component->id),
            'eventListeners' => $component->getEventListeners(),
        ];
    }

    /**
     * Re-render a component and generate patches.
     *
     * @return array<string, mixed>
     */
    public function renderUpdate(Component $component): array
    {
        $html = $this->renderComponentView($component);
        $newVdom = $this->parser->parse($html);

        $oldVdom = $this->snapshots[$component->id] ?? null;

        // Generate patches
        $patches = $this->diffEngine->diff($oldVdom, $newVdom);
        $patches = $this->diffEngine->optimizePatches($patches);

        $this->snapshots[$component->id] = $newVdom;

        $state = $component->getState();

        // Normalize state for signature generation (convert empty strings to null to match request format)
        $normalizedState = $this->normalizeStateForSigning($state);

        $result = [
            'id' => $component->id,
            'patches' => $patches,
            'state' => $state,
            'fingerprint' => $component->calculateFingerprint(),
            'signature' => StateSigner::sign($normalizedState, $component->id),
        ];

        // Include errors if any exist
        if ($component->getErrorBag()->isNotEmpty()) {
            $result['errors'] = $component->getErrorBag()->toArray();
        }

        // Include query string for URL-bound properties
        $queryString = $component->getQueryString();
        if (! empty($queryString)) {
            $result['queryString'] = $queryString;
        }

        // Include dispatched events
        $dispatchedEvents = $component->getDispatchedEvents();
        if (! empty($dispatchedEvents)) {
            $result['events'] = $dispatchedEvents;
        }

        // Include browser events
        $browserEvents = $component->getBrowserEvents();
        if (! empty($browserEvents)) {
            $result['browserEvents'] = $browserEvents;
        }

        // Clear events after adding to response (only if there were any)
        if (! empty($dispatchedEvents) || ! empty($browserEvents)) {
            $component->clearDispatchedEvents();
        }

        return $result;
    }

    /**
     * Normalize state for signature generation.
     * Converts empty strings to null to match how Laravel receives them from requests.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    protected function normalizeStateForSigning(array $state): array
    {
        foreach ($state as $key => $value) {
            if ($value === '') {
                // Empty strings become null in Laravel request input
                $state[$key] = null;
            } elseif (is_array($value)) {
                if (isset($value['__paginator']) && $value['__paginator'] === true) {
                    ksort($value);
                    if (isset($value['items']) && is_array($value['items'])) {
                        $value['items'] = $value['items'];
                    }
                    $state[$key] = $value;
                } else {
                    $state[$key] = $this->normalizeStateForSigning($value);
                }
            }
        }

        return $state;
    }

    /**
     * Render component view to HTML.
     */
    protected function renderComponentView(Component $component): string
    {
        $view = $component->render();

        if (is_string($view)) {
            return $view;
        }

        // $view is guaranteed to be View instance at this point (from Component::render() return type)
        $errorBag = new ViewErrorBag();
        $errorBag->put('default', $component->getErrorBag());

        $reflection = new ReflectionClass($component);
        $trackedProperty = $reflection->getProperty('tracked');
        $hiddenProperty = $reflection->getProperty('hidden');

        $tracked = $trackedProperty->getValue($component);
        $hidden = $hiddenProperty->getValue($component);

        $data = [];
        foreach ($tracked as $property) {
            if (! in_array($property, $hidden) && property_exists($component, $property)) {
                $data[$property] = $component->$property;
            }
        }

        $data['errors'] = $errorBag;
        $data['component'] = $component; // Make component instance available in view

        return $view->with($data)->render();
    }

    /**
     * Get stored snapshot for a component.
     */
    public function getSnapshot(string $componentId): ?VNode
    {
        return $this->snapshots[$componentId] ?? null;
    }

    /**
     * Create and store a snapshot for a component.
     */
    public function snapshotComponent(Component $component): void
    {
        $html = $this->renderComponentView($component);
        $vdom = $this->parser->parse($html);
        $this->snapshots[$component->id] = $vdom;
    }

    /**
     * Clear snapshot for a component.
     */
    public function clearSnapshot(string $componentId): void
    {
        unset($this->snapshots[$componentId]);
    }

    /**
     * Clear all snapshots.
     */
    public function clearAllSnapshots(): void
    {
        $this->snapshots = [];
    }
}
