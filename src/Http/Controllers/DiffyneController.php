<?php

namespace Diffyne\Http\Controllers;

use BadMethodCallException;
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

    public function __construct(
        ComponentHydrator $hydrator,
        Renderer $renderer,
        PatchSerializer $serializer
    ) {
        $this->hydrator = $hydrator;
        $this->renderer = $renderer;
        $this->serializer = $serializer;
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
            $previousHtml = $request->input('previousHtml');

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
            $response = $this->renderer->renderUpdate($component, $previousHtml);

            return response()->json($this->serializer->toResponse($response));

        } catch (ValidationException $e) {
            // Return validation errors
            return response()->json([
                's' => false,
                'error' => 'Validation failed',
                'type' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (BadMethodCallException $e) {
            $response = $this->renderer->renderUpdate($component, $previousHtml);

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
