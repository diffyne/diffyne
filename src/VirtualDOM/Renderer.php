<?php

namespace Diffyne\VirtualDOM;

use Diffyne\Component;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View as IlluminateView;

/**
 * Renderer for Diffyne components that generates Virtual DOM and patches.
 */
class Renderer
{
    protected HTMLParser $parser;

    protected DiffEngine $diffEngine;

    /**
     * Component Virtual DOM snapshots.
     */
    protected array $snapshots = [];

    public function __construct()
    {
        $this->parser = new HTMLParser;
        $this->diffEngine = new DiffEngine;
    }

    /**
     * Render a component and return initial HTML with metadata.
     */
    public function renderInitial(Component $component): array
    {
        $html = $this->renderComponentView($component);
        $vdom = $this->parser->parse($html);

        // Store snapshot for future diffs
        $this->snapshots[$component->id] = $vdom;

        return [
            'id' => $component->id,
            'html' => $html,
            'state' => $component->getState(),
            'fingerprint' => $component->calculateFingerprint(),
            'eventListeners' => $component->getEventListeners(),
        ];
    }

    /**
     * Re-render a component and generate patches.
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

        $result = [
            'id' => $component->id,
            'patches' => $patches,
            'state' => $component->getState(),
            'fingerprint' => $component->calculateFingerprint(),
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
     * Render the component's view to HTML.
     */
    protected function renderComponentView(Component $component): string
    {
        $view = $component->render();

        if (is_string($view)) {
            return $view;
        }

        if ($view instanceof IlluminateView) {
            // Pass component state and errors to the view
            $errorBag = new ViewErrorBag;
            $errorBag->put('default', $component->getErrorBag());

            $data = array_merge(
                $component->getState(),
                ['errors' => $errorBag]
            );

            return $view->with($data)->render();
        }

        throw new \InvalidArgumentException('Component render() must return a string or View instance.');
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
