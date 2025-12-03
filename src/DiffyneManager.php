<?php

namespace Diffyne;

use Diffyne\State\ComponentHydrator;
use Diffyne\VirtualDOM\Renderer;
use Illuminate\Contracts\Foundation\Application;

class DiffyneManager
{
    protected Application $app;

    protected Renderer $renderer;

    protected ComponentHydrator $hydrator;

    /**
     * Registered component aliases.
     */
    protected array $components = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->renderer = $app->make(Renderer::class);
        $this->hydrator = $app->make(ComponentHydrator::class);
    }

    /**
     * Register a component alias.
     */
    public function component(string $alias, string $class): void
    {
        $this->components[$alias] = $class;
    }

    /**
     * Mount a component and return its initial HTML.
     */
    public function mount(string $component, array $params = []): string
    {
        $componentClass = $this->resolveComponent($component);

        if (! $componentClass) {
            throw new \InvalidArgumentException("Component [{$component}] not found.");
        }

        $instance = $this->hydrator->mount($componentClass, $params);
        $rendered = $this->renderer->renderInitial($instance);

        return $this->wrapComponent($rendered, $componentClass);
    }

    /**
     * Resolve component class from alias or fully qualified name.
     */
    protected function resolveComponent(string $component): ?string
    {
        // Check registered aliases
        if (isset($this->components[$component])) {
            return $this->components[$component];
        }

        // Check if it's a fully qualified class name
        if (class_exists($component)) {
            return $component;
        }

        // Try to find in default namespace (support nested paths with forward slashes)
        $defaultNamespace = config('diffyne.component_namespace', 'App\\Diffyne');
        $componentPath = str_replace('/', '\\', $component);
        $fullClass = $defaultNamespace.'\\'.$componentPath;

        if (class_exists($fullClass)) {
            return $fullClass;
        }

        return null;
    }

    /**
     * Wrap rendered component in container div with metadata.
     */
    protected function wrapComponent(array $rendered, string $componentClass): string
    {
        $id = $rendered['id'];
        $html = $rendered['html'];
        $state = htmlspecialchars(json_encode($rendered['state']), ENT_QUOTES, 'UTF-8');
        $fingerprint = $rendered['fingerprint'];

        // For nested components, use the full path as component name
        $namespace = config('diffyne.component_namespace', 'App\\Diffyne');
        $componentName = str_replace($namespace.'\\', '', $componentClass);
        $componentName = str_replace('\\', '/', $componentName);

        return <<<HTML
<div 
    diff:id="{$id}"
    diff:class="{$componentClass}"
    diff:name="{$componentName}"
    diff:state="{$state}"
    diff:fingerprint="{$fingerprint}"
    data-diffyne-component
>
    {$html}
</div>
HTML;
    }

    /**
     * Get the renderer instance.
     */
    public function getRenderer(): Renderer
    {
        return $this->renderer;
    }

    /**
     * Get the hydrator instance.
     */
    public function getHydrator(): ComponentHydrator
    {
        return $this->hydrator;
    }

    /**
     * Get all registered components.
     */
    public function getComponents(): array
    {
        return $this->components;
    }
}
