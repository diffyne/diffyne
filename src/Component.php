<?php

namespace Diffyne;

use Diffyne\Attributes\Computed;
use Diffyne\Attributes\Invokable;
use Diffyne\Attributes\Locked;
use Diffyne\Attributes\On;
use Diffyne\Attributes\QueryString;
use Diffyne\FileUpload\FileUploadService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use TypeError;

abstract class Component
{
    /**
     * The component's unique identifier.
     */
    public string $id;

    /**
     * The component's data fingerprint for change detection.
     */
    protected string $fingerprint = '';

    /**
     * Component properties that should not be exposed to the client.
     *
     * @var array<string>
     */
    protected array $hidden = [];

    /**
     * Component properties that should be tracked for changes.
     *
     * @var array<string>
     */
    protected array $tracked = [];

    /**
     * Properties that are locked (cannot be updated from client).
     *
     * @var array<string>
     */
    protected array $locked = [];

    /**
     * Properties that are computed (derived, not stored).
     *
     * @var array<string>
     */
    protected array $computed = [];

    /**
     * Component properties that should be synced with URL query string.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $urlProperties = [];

    /**
     * Previous state snapshot for diff calculation.
     *
     * @var array<string, mixed>
     */
    private array $previousState = [];

    /**
     * Validation error bag.
     */
    protected MessageBag $errorBag;

    /**
     * Events to dispatch to other components.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $dispatchedEvents = [];

    /**
     * Browser events to dispatch.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $browserEvents = [];

    /**
     * Event listeners registered via #[On] attribute.
     *
     * @var array<string, array<int, string>>
     */
    protected array $eventListeners = [];

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->id = $this->generateId();
        $this->errorBag = new MessageBag();
        $this->initializeProperties();
        $this->registerEventListeners();
    }

    /**
     * Generate a unique component ID.
     */
    protected function generateId(): string
    {
        return 'diffyne-'.Str::random(16);
    }

    /**
     * Initialize component properties.
     */
    protected function initializeProperties(): void
    {
        // Track all public properties by default
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            if (! $property->isStatic() && $property->getName() !== 'id') {
                $propertyName = $property->getName();

                // Check for Computed attribute
                $computedAttrs = $property->getAttributes(Computed::class);
                if (! empty($computedAttrs)) {
                    $this->computed[] = $propertyName;
                    $this->hidden[] = $propertyName; // Computed properties are also hidden from state

                    continue; // Don't track computed properties
                }

                // Check for Locked attribute
                $lockedAttrs = $property->getAttributes(Locked::class);
                if (! empty($lockedAttrs)) {
                    $this->locked[] = $propertyName;
                }

                $this->tracked[] = $propertyName;

                // Check for QueryString attribute
                $attributes = $property->getAttributes(QueryString::class);
                if (! empty($attributes)) {
                    $urlAttr = $attributes[0]->newInstance();
                    $this->urlProperties[$propertyName] = [
                        'as' => $urlAttr->as ?? $propertyName,
                        'history' => $urlAttr->history,
                        'keep' => $urlAttr->keep,
                    ];
                }
            }
        }
    }

    /**
     * Register event listeners from #[On] attributes.
     */
    protected function registerEventListeners(): void
    {
        $reflection = new ReflectionClass($this);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip magic methods and static methods
            if (str_starts_with($method->getName(), '__') || $method->isStatic()) {
                continue;
            }

            // Get all #[On] attributes on this method
            $attributes = $method->getAttributes(On::class);

            foreach ($attributes as $attribute) {
                /** @var On $onAttribute */
                $onAttribute = $attribute->newInstance();
                $eventName = $onAttribute->event;

                // Register this method as a listener for the event
                if (! isset($this->eventListeners[$eventName])) {
                    $this->eventListeners[$eventName] = [];
                }

                $this->eventListeners[$eventName][] = $method->getName();
            }
        }
    }

    /**
     * Get registered event listeners.
     *
     * @return array<string, array<int, string>>
     */
    public function getEventListeners(): array
    {
        return $this->eventListeners;
    }

    /**
     * Handle an incoming event by calling registered listeners.
     *
     * @param array<int, mixed> $params
     */
    public function handleEvent(string $eventName, array $params = []): void
    {
        if (! isset($this->eventListeners[$eventName])) {
            return;
        }

        foreach ($this->eventListeners[$eventName] as $methodName) {
            if (method_exists($this, $methodName)) {
                $this->$methodName(...$params);
            }
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    abstract public function render(): View|string;

    /**
     * Get the component's view name.
     */
    protected function view(): string
    {
        $classWithoutPackageNamespace = str_replace(config('diffyne.component_namespace', 'App\\Diffyne').'\\', '', static::class);
        $class = str_replace('\\', '/', $classWithoutPackageNamespace);
        $parts = explode('/', $class);

        // Convert each part to kebab-case
        $parts = array_map(fn ($part) => Str::kebab($part), $parts);

        // Join with dots
        return 'diffyne.' . implode('.', $parts);
    }

    /**
     * Lifecycle hook: Called before the component is rendered for the first time.
     */
    public function mount(): void
    {
        //
    }

    /**
     * Lifecycle hook: Called after the component is hydrated on the client.
     */
    public function hydrate(): void
    {
        //
    }

    /**
     * Lifecycle hook: Called before a property is updated.
     */
    public function updating(string $property, mixed $value): void
    {
        //
    }

    /**
     * Lifecycle hook: Called after a property is updated.
     */
    public function updated(string $property): void
    {
        //
    }

    /**
     * Lifecycle hook: Called before dehydrating the component state.
     */
    public function dehydrate(): void
    {
        //
    }

    /**
     * Update a component property.
     */
    public function updateProperty(string $property, mixed $value): void
    {
        if (! property_exists($this, $property)) {
            throw new \InvalidArgumentException("Property [{$property}] does not exist on component.");
        }

        if (in_array($property, $this->hidden)) {
            throw new \InvalidArgumentException("Property [{$property}] is protected and cannot be updated.");
        }

        if (in_array($property, $this->locked)) {
            throw new \InvalidArgumentException("Property [{$property}] is locked and cannot be updated from client.");
        }

        if (in_array($property, $this->computed)) {
            throw new \InvalidArgumentException("Property [{$property}] is computed and cannot be updated.");
        }

        $this->updating($property, $value);

        $reflection = new ReflectionProperty($this, $property);
        $type = $reflection->getType();

        if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
            if (! is_array($value)) {
                $value = $value !== null ? [$value] : [];
            }
        }

        $this->$property = $value;

        $this->updated($property);
    }

    /**
     * Call a component method.
     *
     * @param array<int, mixed> $params
     */
    public function callMethod(string $method, array $params = []): mixed
    {
        if (! method_exists($this, $method)) {
            throw new \BadMethodCallException("Method [{$method}] does not exist on component.");
        }

        // Check if method is explicitly marked as invokable
        $reflection = new \ReflectionMethod($this, $method);
        $invokableAttrs = $reflection->getAttributes(Invokable::class);

        if (empty($invokableAttrs)) {
            throw new \BadMethodCallException("Method [{$method}] is not invokable. Add #[Invokable] attribute to allow client invocation.");
        }

        return $this->$method(...$params);
    }

    /**
     * Get methods that cannot be called from the client.
     *
     * @return array<int, string>
     */
    protected function getProtectedMethods(): array
    {
        return [
            'mount',
            'hydrate',
            'updating',
            'updated',
            'dehydrate',
            'render',
            'toArray',
            'getState',
            'restoreState',
            'getChanges',
            'snapshot',
            'updateProperty',
            'callMethod',
            'validate',
            'validateOnly',
            'resetValidation',
            'resetErrorBag',
            'addError',
            'getErrorBag',
            'setErrorBag',
            'rules',
            'messages',
            'validationAttributes',
        ];
    }

    /**
     * Validate component properties.
     *
     * @param array<string, string>|null $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     * @throws ValidationException
     */
    protected function validate(?array $rules = null, array $messages = [], array $attributes = []): array
    {
        $rules = $rules ?? $this->rules();
        $messages = array_merge($this->messages(), $messages);
        $attributes = array_merge($this->validationAttributes(), $attributes);

        $validator = Validator::make(
            $this->getState(),
            $rules,
            $messages,
            $attributes
        );

        try {
            $validated = $validator->validate();
            $this->resetValidation();

            return $validated;
        } catch (ValidationException $e) {
            $this->errorBag = $e->validator->errors();

            throw $e;
        }
    }

    /**
     * Validate a single property.
     */
    protected function validateOnly(string $field): void
    {
        $rules = $this->rules();

        if (! isset($rules[$field])) {
            return;
        }

        $validator = Validator::make(
            $this->getState(),
            [$field => $rules[$field]],
            $this->messages(),
            $this->validationAttributes()
        );

        if ($validator->fails()) {
            $this->errorBag->merge($validator->errors()->messages());
        } else {
            $this->errorBag->forget($field);
        }
    }

    /**
     * Reset validation for specific fields or all fields.
     *
     * @param string|array<string>|null $field
     */
    protected function resetValidation($field = null): void
    {
        if ($field === null) {
            $this->errorBag = new MessageBag();

            return;
        }

        $fields = is_array($field) ? $field : [$field];

        foreach ($fields as $f) {
            $this->errorBag->forget($f);
        }
    }

    /**
     * Alias for resetValidation().
     *
     * @param string|array<string>|null $field
     */
    protected function resetErrorBag($field = null): void
    {
        $this->resetValidation($field);
    }

    /**
     * Add a validation error.
     */
    protected function addError(string $field, string $message): void
    {
        $this->errorBag->add($field, $message);
    }

    /**
     * Get the validation rules.
     *
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Get custom validation attributes.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [];
    }

    /**
     * Get the error bag.
     */
    public function getErrorBag(): MessageBag
    {
        return $this->errorBag;
    }

    /**
     * Set the error bag (for hydration).
     *
     * @param array<string, array<int, string>> $errors
     */
    public function setErrorBag(array $errors): void
    {
        $this->errorBag = new MessageBag($errors);
    }

    /**
     * Get the component's current state.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $state = [];

        foreach ($this->tracked as $property) {
            if (! in_array($property, $this->hidden) && property_exists($this, $property)) {
                $value = $this->$property;
                if ($value instanceof LengthAwarePaginator) {
                    $state[$property] = $this->serializePaginator($value);
                } else {
                    $state[$property] = $value;
                }
            }
        }

        return $state;
    }

    /**
     * Serialize a LengthAwarePaginator to an array.
     *
     * @return array<string, mixed>
     */
    protected function serializePaginator(LengthAwarePaginator $paginator): array //@phpstan-ignore-line
    {
        // Convert items to array if they're objects
        $items = $paginator->items();
        $itemsArray = [];
        foreach ($items as $item) {
            if (is_object($item)) {
                if (method_exists($item, 'toArray')) {
                    $itemsArray[] = $item->toArray();
                } else {
                    $itemsArray[] = (array) $item;
                }
            } else {
                $itemsArray[] = $item;
            }
        }

        return [
            '__paginator' => true,
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'first_item' => $paginator->firstItem(),
            'last_item' => $paginator->lastItem(),
            'items' => $itemsArray,
            'path' => $paginator->path(),
        ];
    }

    /**
     * Restore component state from an array.
     *
     * @param array<string, mixed> $state
     */
    public function restoreState(array $state): void
    {
        foreach ($state as $property => $value) {
            if (property_exists($this, $property) && ! in_array($property, $this->hidden)) {
                try {
                    if (is_array($value) && isset($value['__paginator']) && $value['__paginator'] === true) {
                        $this->$property = $this->deserializePaginator($value);
                    } else {
                        $this->$property = $value;
                    }
                } catch (TypeError $e) {
                    $reflection = new ReflectionProperty($this, $property);
                    $type = $reflection->getType();

                    if ($type && ! $type->allowsNull() && $value === null) {
                        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
                        $this->$property = match ($typeName) {
                            'string' => '',
                            'int' => 0,
                            'float' => 0.0,
                            'bool' => false,
                            'array' => [],
                            default => $value,
                        };
                    } else {
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * Deserialize an array back to a LengthAwarePaginator.
     *
     * @param array<string, mixed> $data
     */
    protected function deserializePaginator(array $data): LengthAwarePaginator //@phpstan-ignore-line
    {
        $items = $data['items'] ?? [];
        $total = $data['total'] ?? 0;
        $perPage = $data['per_page'] ?? 15;
        $currentPage = $data['current_page'] ?? 1;
        $path = $data['path'] ?? request()->url();

        $items = array_map(function ($item) {
            if (is_array($item)) {
                return (object) $item;
            }

            return $item;
        }, $items);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $path,
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Take a snapshot of current state for diff comparison.
     */
    public function snapshot(): void
    {
        $this->previousState = $this->getState();
        $this->fingerprint = $this->calculateFingerprint();
    }

    /**
     * Get changes since last snapshot.
     *
     * @return array<string, mixed>
     */
    public function getChanges(): array
    {
        $current = $this->getState();
        $changes = [];

        foreach ($current as $key => $value) {
            if (! isset($this->previousState[$key]) || $this->previousState[$key] !== $value) {
                $changes[$key] = [
                    'old' => $this->previousState[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * Calculate a fingerprint of the current state.
     */
    public function calculateFingerprint(): string
    {
        $json = json_encode($this->getState());
        if ($json === false) {
            return '';
        }

        return md5($json);
    }

    /**
     * Get the current fingerprint.
     */
    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * Check if the component state has changed.
     */
    public function hasChanged(): bool
    {
        return $this->fingerprint !== $this->calculateFingerprint();
    }

    /**
     * Get URL-bound properties configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getUrlProperties(): array
    {
        return $this->urlProperties;
    }

    /**
     * Get query string parameters from URL-bound properties.
     *
     * @return array<string, mixed>
     */
    public function getQueryString(): array
    {
        $query = [];

        foreach ($this->urlProperties as $property => $config) {
            $value = $this->$property ?? null;
            $key = $config['as'];

            // Skip empty values unless keep is true
            if (! $config['keep'] && ($value === null || $value === '')) {
                continue;
            }

            $query[$key] = $value;
        }

        return $query;
    }

    /**
     * Redirect to a URL using SPA navigation (client-side).
     */
    protected function redirect(string $url, bool $spa = true): never
    {
        $response = [
            'redirect' => [
                'url' => $url,
                'spa' => $spa,
            ],
        ];

        throw new \Diffyne\Exceptions\RedirectException($response);
    }

    /**
     * Redirect to a route using SPA navigation.
     *
     * @param array<string, mixed> $parameters
     */
    protected function redirectRoute(string $name, array $parameters = [], bool $spa = true): never
    {
        $url = route($name, $parameters);
        $this->redirect($url, $spa);
    }

    /**
     * Redirect to the previous URL.
     */
    protected function redirectBack(bool $spa = true): never
    {
        $url = url()->previous();
        $this->redirect($url, $spa);
    }

    /**
     * Dispatch an event to all components listening for it.
     *
     * @param array<int, mixed> $params
     */
    protected function dispatch(string $event, ...$params): self
    {
        $this->dispatchedEvents[] = [
            'name' => $event,
            'params' => $params,
            'to' => null,
            'self' => false,
        ];

        return $this;
    }

    /**
     * Dispatch an event to a specific component(s).
     *
     * @param string|array<string> $components
     * @param array<int, mixed> $params
     */
    protected function dispatchTo(string|array $components, string $event, ...$params): self
    {
        $this->dispatchedEvents[] = [
            'name' => $event,
            'params' => $params,
            'to' => is_array($components) ? $components : [$components],
            'self' => false,
        ];

        return $this;
    }

    /**
     * Dispatch an event only to this component.
     *
     * @param array<int, mixed> $params
     */
    protected function dispatchSelf(string $event, ...$params): self
    {
        $this->dispatchedEvents[] = [
            'name' => $event,
            'params' => $params,
            'to' => [$this->id],
            'self' => true,
        ];

        return $this;
    }

    /**
     * Dispatch a browser event (JavaScript custom event).
     */
    protected function dispatchBrowserEvent(string $event, mixed $data = null): self
    {
        $this->browserEvents[] = [
            'name' => $event,
            'data' => $data,
        ];

        return $this;
    }

    /**
     * Get dispatched events.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    /**
     * Get browser events.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBrowserEvents(): array
    {
        return $this->browserEvents;
    }

    /**
     * Clear dispatched events (used after sending to client).
     */
    public function clearDispatchedEvents(): void
    {
        $this->dispatchedEvents = [];
        $this->browserEvents = [];
    }

    /**
     * Get preview URL for temporary file.
     * This is a public method that can be called from Blade views.
     */
    public function getTemporaryFilePreviewUrl(string $identifier): string
    {
        return route('diffyne.preview', ['id' => $identifier]);
    }

    /**
     * Move temporary file to permanent storage.
     *
     * @param string $identifier Temporary file identifier (from upload)
     * @param string $destinationPath Destination path (e.g., 'avatars/user-123.jpg')
     * @param string|null $disk Storage disk (defaults to config)
     * @return string|null Permanent file path or null on failure
     */
    protected function moveTemporaryFile(string $identifier, string $destinationPath, ?string $disk = null): ?string
    {
        $service = app(FileUploadService::class);

        return $service->moveToPermanent($identifier, $destinationPath, $disk);
    }

    /**
     * Delete temporary file.
     */
    protected function deleteTemporaryFile(string $identifier): bool
    {
        $service = app(FileUploadService::class);

        return $service->deleteTemporary($identifier);
    }

    /**
     * Cleanup old temporary files.
     * This is a static method that can be called from anywhere.
     *
     * @return int Number of files deleted
     */
    public static function cleanupTemporaryFiles(): int
    {
        $service = app(FileUploadService::class);

        return $service->cleanupOldFiles();
    }

    /**
     * Convert the component to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => static::class,
            'state' => $this->getState(),
            'fingerprint' => $this->fingerprint,
        ];
    }

    /**
     * Dynamically set a property.
     */
    public function __set(string $name, mixed $value): void
    {
        if (property_exists($this, $name)) {
            $this->updateProperty($name, $value);
        }
    }

    /**
     * Dynamically get a property.
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new \InvalidArgumentException("Property [{$name}] does not exist on component.");
    }
}
