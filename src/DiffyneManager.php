<?php

namespace Diffyne;

use Diffyne\Attributes\Lazy;
use Diffyne\State\ComponentHydrator;
use Diffyne\VirtualDOM\Renderer;
use Illuminate\Contracts\Foundation\Application;
use ReflectionClass;

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

        $reflection = new ReflectionClass($componentClass);
        $lazyAttributes = $reflection->getAttributes(Lazy::class);

        if (! empty($lazyAttributes)) {
            return $this->mountLazy($componentClass, $params, $lazyAttributes[0]->newInstance());
        }

        $instance = $this->hydrator->mount($componentClass, $params);
        $rendered = $this->renderer->renderInitial($instance);

        return $this->wrapComponent($rendered, $componentClass);
    }

    /**
     * Mount a lazy-loaded component with placeholder.
     */
    protected function mountLazy(string $componentClass, array $params, Lazy $lazyAttr): string
    {
        $id = 'diffyne-'.\Illuminate\Support\Str::random(16);
        $paramsEncoded = htmlspecialchars(json_encode($params), ENT_QUOTES, 'UTF-8');

        // For nested components, use the full path as component name
        $namespace = config('diffyne.component_namespace', 'App\\Diffyne');
        $componentName = str_replace($namespace.'\\', '', $componentClass);
        $componentName = str_replace('\\', '/', $componentName);

        // Default placeholder
        $placeholder = $lazyAttr->placeholder ?? $this->getDefaultPlaceholder();

        return <<<HTML
<div 
    diff:id="{$id}"
    diff:class="{$componentClass}"
    diff:name="{$componentName}"
    diff:params="{$paramsEncoded}"
    data-diffyne-lazy
    data-diffyne-component
>
    {$placeholder}
</div>
HTML;
    }

    /**
     * Get default lazy loading placeholder.
     */
    protected function getDefaultPlaceholder(): string
    {
        return <<<'HTML'
<div class="diffyne-lazy-placeholder" style="padding: 2rem; text-align: center; color: #666;">
    <div class="diffyne-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; animation: diffyne-spin 1s linear infinite;"></div>
    <style>
        @keyframes diffyne-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</div>
HTML;
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
        $signature = $rendered['signature'];
        $eventListeners = htmlspecialchars(json_encode($rendered['eventListeners'] ?? []), ENT_QUOTES, 'UTF-8');

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
    diff:listeners="{$eventListeners}"
    diff:signature="{$signature}"
    data-diffyne-component
    data-diffyne-loaded
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
