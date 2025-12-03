<?php

namespace Diffyne\Http\Controllers;

use BadMethodCallException;
use Diffyne\DiffyneManager;
use Diffyne\Exceptions\RedirectException;
use Diffyne\State\ComponentHydrator;
use Diffyne\VirtualDOM\PatchSerializer;
use Diffyne\VirtualDOM\Renderer;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DiffyneController extends Controller
{
    protected ComponentHydrator $hydrator;

    protected Renderer $renderer;

    protected PatchSerializer $serializer;

    protected DiffyneManager $manager;

    public function __construct(
        ComponentHydrator $hydrator,
        Renderer $renderer,
        PatchSerializer $serializer,
        DiffyneManager $manager
    ) {
        $this->hydrator = $hydrator;
        $this->renderer = $renderer;
        $this->serializer = $serializer;
        $this->manager = $manager;
    }

    /**
     * Handle component updates.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type');
            $componentId = $request->input('componentId');
            $state = $request->input('state', []);
            $fingerprint = $request->input('fingerprint');

            // Validate request
            if (! $componentId || ! $state) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid request',
                ], 400);
            }

            // Get component class from state or registry
            $componentClass = $this->resolveComponentClass($request);

            if (! $componentClass) {
                return response()->json([
                    'success' => false,
                    'error' => 'Component class not found',
                ], 404);
            }

            // Hydrate component from state
            $component = $this->hydrator->hydrate($componentClass, $state, $componentId);

            // Store initial snapshot for diffing
            $this->renderer->snapshotComponent($component);

            // Restore error bag if present
            if ($request->has('errors')) {
                $component->setErrorBag($request->input('errors', []));
            }

            // Handle different request types
            switch ($type) {
                case 'call':
                    $method = $request->input('method');
                    $params = $request->input('params', []);

                    if (! $method) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Method not specified',
                        ], 400);
                    }

                    $component->callMethod($method, $params);
                    break;

                case 'update':
                    $property = $request->input('property');
                    $value = $request->input('value');

                    if (! $property) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Property not specified',
                        ], 400);
                    }

                    $component->updateProperty($property, $value);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid request type',
                    ], 400);
            }

            // Render updates and generate patches
            $response = $this->renderer->renderUpdate($component);

            // Optimize response
            $serializedResponse = $this->serializer->toResponse($response, config('diffyne.performance.minify_patches', true));

            return response()->json($serializedResponse)
                ->header('Content-Type', 'application/json; charset=utf-8');

        } catch (RedirectException $e) {
            $redirectData = $e->getRedirectData();

            return response()->json([
                's' => true,
                'redirect' => $redirectData['redirect'],
            ]);
        } catch (ValidationException $e) {
            // Return validation errors
            return response()->json([
                's' => false,
                'error' => 'Validation failed',
                'type' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (BadMethodCallException $e) {
            $response = $this->renderer->renderUpdate($component);

            return response()->json($this->serializer->toResponse($response));

        } catch (BadMethodCallException $e) {
            return response()->json([
                's' => false,
                'error' => $e->getMessage(),
                'type' => 'method_error',
            ], 400);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                's' => false,
                'error' => $e->getMessage(),
                'type' => 'property_error',
            ], 400);
        } catch (Exception $e) {
            Log::error('Diffyne Error: '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (config('diffyne.debug', false)) {
                return response()->json([
                    's' => false,
                    'error' => $e->getMessage(),
                    'type' => 'exception',
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ], 500);
            }

            return response()->json([
                's' => false,
                'error' => 'An error occurred while processing your request.',
                'type' => 'server_error',
            ], 500);
        }
    }

    /**
     * Resolve component class from request.
     */
    protected function resolveComponentClass(Request $request): ?string
    {
        // Try to get from request
        $componentClass = $request->input('componentClass');

        if ($componentClass && class_exists($componentClass)) {
            return $componentClass;
        }

        // Try to get from component name
        $componentName = $request->input('componentName');

        if ($componentName) {
            $defaultNamespace = config('diffyne.component_namespace', 'App\\Diffyne');

            $componentName = str_replace('/', '\\', $componentName);
            $fullClass = $defaultNamespace.'\\'.$componentName;

            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        return null;
    }

    /**
     * Load a lazy component.
     */
    public function loadLazy(Request $request): JsonResponse
    {
        try {
            $componentClass = $request->input('componentClass');
            $componentId = $request->input('componentId');
            $params = $request->input('params', []);
            $queryParams = $request->input('queryParams', []);

            if (! $componentClass || ! class_exists($componentClass)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid component class',
                ], 400);
            }

            // Merge query parameters with component params
            // Query parameters take precedence for QueryString properties
            $mergedParams = array_merge($params, $queryParams);

            // Mount the component
            $instance = $this->hydrator->mount($componentClass, $mergedParams);
            $rendered = $this->renderer->renderInitial($instance);

            // Return the rendered HTML and state
            return response()->json([
                'success' => true,
                'id' => $rendered['id'],
                'html' => $rendered['html'],
                'state' => $rendered['state'],
                'fingerprint' => $rendered['fingerprint'],
            ]);

        } catch (Exception $e) {
            Log::error('Lazy Load Error: '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load component: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check endpoint.
     */

    /**
     * Health check endpoint.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => '1.0.0',
            'transport' => config('diffyne.transport', 'ajax'),
        ]);
    }
}
