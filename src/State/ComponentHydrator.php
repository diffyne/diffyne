<?php

namespace Diffyne\State;

use Diffyne\Component;

class ComponentHydrator
{
    /**
     * Hydrate a component from serialized state.
     */
    public function hydrate(string $componentClass, array $state, string $id): Component
    {
        if (! class_exists($componentClass)) {
            throw new \InvalidArgumentException("Component class [{$componentClass}] does not exist.");
        }

        if (! is_subclass_of($componentClass, Component::class)) {
            throw new \InvalidArgumentException("Class [{$componentClass}] must extend Diffyne\\Component.");
        }

        $component = new $componentClass;
        $component->id = $id;
        $component->restoreState($state);
        $component->hydrate();
        $component->snapshot();

        return $component;
    }

    /**
     * Dehydrate a component to serialized state.
     */
    public function dehydrate(Component $component): array
    {
        $component->dehydrate();

        return [
            'id' => $component->id,
            'name' => get_class($component),
            'state' => $component->getState(),
            'fingerprint' => $component->calculateFingerprint(),
        ];
    }

    /**
     * Create a fresh component instance.
     */
    public function mount(string $componentClass, array $params = []): Component
    {
        if (! class_exists($componentClass)) {
            throw new \InvalidArgumentException("Component class [{$componentClass}] does not exist.");
        }

        if (! is_subclass_of($componentClass, Component::class)) {
            throw new \InvalidArgumentException("Class [{$componentClass}] must extend Diffyne\\Component.");
        }

        $component = new $componentClass;

        // Sync URL-bound properties from query string
        $this->syncFromQueryString($component);

        // Set initial properties from parameters
        foreach ($params as $key => $value) {
            if (property_exists($component, $key)) {
                $component->$key = $value;
            }
        }

        $component->mount();
        $component->snapshot();

        return $component;
    }

    /**
     * Sync component properties from URL query string.
     */
    protected function syncFromQueryString(Component $component): void
    {
        $urlProperties = $component->getUrlProperties();

        foreach ($urlProperties as $property => $config) {
            $queryKey = $config['as'];
            $value = request()->query($queryKey);

            if ($value !== null && property_exists($component, $property)) {
                $component->$property = $value;
            }
        }
    }
}
