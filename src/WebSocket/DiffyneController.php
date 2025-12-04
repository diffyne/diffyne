<?php

namespace Diffyne\WebSocket;

use Diffyne\DiffyneManager;
use Diffyne\Exceptions\RedirectException;
use Diffyne\State\ComponentHydrator;
use Diffyne\VirtualDOM\PatchSerializer;
use Diffyne\VirtualDOM\Renderer;
use Sockeon\Sockeon\Controllers\SocketController;
use Sockeon\Sockeon\WebSocket\Attributes\OnConnect;
use Sockeon\Sockeon\WebSocket\Attributes\OnDisconnect;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;

class DiffyneController extends SocketController
{
    protected Renderer $renderer;

    protected ComponentHydrator $hydrator;

    protected DiffyneManager $manager;

    protected PatchSerializer $serializer;

    public function __construct()
    {
        $this->renderer = app(Renderer::class);
        $this->hydrator = app(ComponentHydrator::class);
        $this->manager = app('diffyne');
        $this->serializer = app(PatchSerializer::class);
    }

    /**
     * Handle new WebSocket connections
     */
    #[OnConnect]
    public function onConnect(string $clientId): void
    {
        $this->emit($clientId, 'diffyne.connected', [
            'clientId' => $clientId,
            'message' => 'Connected to Diffyne WebSocket server',
            'timestamp' => time(),
        ]);

        $this->getLogger()->info("Client {$clientId} connected to Diffyne");
    }

    /**
     * Handle WebSocket disconnections
     */
    #[OnDisconnect]
    public function onDisconnect(string $clientId): void
    {
        $this->getLogger()->info("Client {$clientId} disconnected from Diffyne");
    }

    /**
     * Handle component method calls
     */
    #[SocketOn('diffyne.call')]
    public function handleMethodCall(string $clientId, array $data): void
    {
        try {
            $componentClass = $data['componentClass'] ?? null;
            $method = $data['method'] ?? null;
            $params = $data['params'] ?? [];
            $state = $data['state'] ?? [];
            $fingerprint = $data['fingerprint'] ?? null;

            if (! $componentClass || ! $method) {
                $this->emit($clientId, 'diffyne.error', [
                    'error' => 'Missing required parameters',
                    'type' => 'validation_error',
                ]);

                return;
            }

            // Hydrate component from state
            $component = $this->hydrator->hydrate($componentClass, $state, $data['componentId']);

            // Store initial snapshot for diffing
            $this->renderer->snapshotComponent($component);

            // Call the method
            if (! method_exists($component, $method)) {
                $this->emit($clientId, 'diffyne.error', [
                    'error' => "Method {$method} does not exist",
                    'type' => 'method_error',
                ]);

                return;
            }

            call_user_func_array([$component, $method], $params);

            // Render the updated component
            $response = $this->renderer->renderUpdate($component);

            // Serialize response with events
            $serializedResponse = $this->serializer->toResponse($response, config('diffyne.performance.minify_patches', true));

            // Send response
            $this->emit($clientId, 'diffyne.response', array_merge(
                $serializedResponse,
                ['requestId' => $data['requestId'] ?? null]
            ));

        } catch (RedirectException $e) {
            $redirectData = $e->getRedirectData();
            $this->emit($clientId, 'diffyne.response', [
                's' => true,
                'redirect' => $redirectData['redirect'],
                'requestId' => $data['requestId'] ?? null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->emit($clientId, 'diffyne.response', [
                's' => false,
                'error' => 'Validation failed',
                'type' => 'validation_error',
                'errors' => $e->errors(),
                'requestId' => $data['requestId'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->emit($clientId, 'diffyne.response', [
                's' => false,
                'error' => $e->getMessage(),
                'type' => 'exception',
                'details' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                'requestId' => $data['requestId'] ?? null,
            ]);
        }
    }

    /**
     * Handle component property updates
     */
    #[SocketOn('diffyne.update')]
    public function handlePropertyUpdate(string $clientId, array $data): void
    {
        try {
            $componentClass = $data['componentClass'] ?? null;
            $property = $data['property'] ?? null;
            $value = $data['value'] ?? null;
            $state = $data['state'] ?? [];
            $fingerprint = $data['fingerprint'] ?? null;

            if (! $componentClass || ! $property) {
                $this->emit($clientId, 'diffyne.error', [
                    'error' => 'Missing required parameters',
                    'type' => 'validation_error',
                ]);

                return;
            }

            // Hydrate component from state
            $component = $this->hydrator->hydrate($componentClass, $state, $data['componentId']);

            // Store initial snapshot for diffing
            $this->renderer->snapshotComponent($component);

            // Update the property
            if (! property_exists($component, $property)) {
                $this->emit($clientId, 'diffyne.error', [
                    'error' => "Property {$property} does not exist",
                    'type' => 'property_error',
                ]);

                return;
            }

            $component->{$property} = $value;

            // Call updated hook if it exists
            if (method_exists($component, 'updated')) {
                $component->updated($property);
            }

            // Render the updated component
            $response = $this->renderer->renderUpdate($component);

            // Serialize response with events
            $serializedResponse = $this->serializer->toResponse($response, config('diffyne.performance.minify_patches', true));

            // Send response
            $this->emit($clientId, 'diffyne.response', array_merge(
                $serializedResponse,
                ['requestId' => $data['requestId'] ?? null]
            ));

        } catch (RedirectException $e) {
            $redirectData = $e->getRedirectData();
            $this->emit($clientId, 'diffyne.response', [
                's' => true,
                'redirect' => $redirectData['redirect'],
                'requestId' => $data['requestId'] ?? null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->emit($clientId, 'diffyne.response', [
                's' => false,
                'error' => 'Validation failed',
                'type' => 'validation_error',
                'errors' => $e->errors(),
                'requestId' => $data['requestId'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->emit($clientId, 'diffyne.response', [
                's' => false,
                'error' => $e->getMessage(),
                'type' => 'exception',
                'details' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                'requestId' => $data['requestId'] ?? null,
            ]);
        }
    }

    /**
     * Handle ping from clients
     */
    #[SocketOn('diffyne.ping')]
    public function handlePing(string $clientId, array $data): void
    {
        $this->emit($clientId, 'diffyne.pong', [
            'timestamp' => time(),
        ]);
    }
}
