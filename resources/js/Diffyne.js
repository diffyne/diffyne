/**
 * Diffyne.js
 * Main entry point - orchestrates all modules (Dependency Inversion Principle)
 */

import { ComponentRegistry } from './core/ComponentRegistry.js';
import { EventBinder } from './core/EventBinder.js';
import { PatchApplier } from './core/PatchApplier.js';
import { VNodeConverter } from './core/VNodeConverter.js';
import { TransportService } from './services/TransportService.js';
import { LoadingService } from './services/LoadingService.js';
import { ErrorService } from './services/ErrorService.js';
import { ModelSyncService } from './services/ModelSyncService.js';
import { EventManager } from './services/EventManager.js';
import { FileUploadService } from './services/FileUploadService.js';
import { parseJSON, parseAction, updateQueryString, getQueryParams, Logger } from './utils/helpers.js';

export class Diffyne {
    constructor(config = {}) {
        this.config = {
            transport: config.transport || 'ajax',
            wsUrl: config.wsUrl || 'ws://localhost:8080/diffyne',
            endpoint: config.endpoint || '/_diffyne',
            debug: config.debug || false,
            maxMessageSize: config.maxMessageSize || 1048576,
            ...config
        };

        // Initialize services (Dependency Injection)
        this.logger = new Logger(this.config.debug);
        this.registry = new ComponentRegistry();
        this.transport = new TransportService(this.config, this.logger, this.maxMessageSize);
        this.patchApplier = new PatchApplier();
        this.vNodeConverter = new VNodeConverter();
        this.loadingService = new LoadingService();
        this.errorService = new ErrorService();
        this.modelSync = new ModelSyncService();
        this.eventManager = new EventManager(this.registry, this.logger);
        this.fileUpload = new FileUploadService(this.config);
        
        // Request tracking for cancellation and sequencing
        this.pendingRequests = new Map();
        this.requestSequence = new Map();
        
        // Initialize event binder with handlers
        this.eventBinder = new EventBinder(
            (id, action, event) => this.handleAction(id, action, event),
            (id, property, value) => this.handleModelUpdate(id, property, value),
            (id, property, value) => this.updateLocalState(id, property, value),
            (id, property, file, isMultiple) => this.handleFileUpload(id, property, file, isMultiple)
        );

        this.init();
    }

    /**
     * Initialize Diffyne
     */
    init() {
        this.logger.log('Initializing Diffyne...');

        if (this.config.transport === 'websocket') {
            this.transport.connectWebSocket(
                () => this.logger.log('WebSocket connected'),
                () => this.logger.log('WebSocket disconnected'),
                (error) => this.logger.error('WebSocket error:', error)
            );
        }

        this.hydrateComponents();
        setTimeout(() => this.loadLazyComponents(), 100);
        this.observeDOMChanges();

        // Listen for custom action events from EventManager
        document.addEventListener('diff:action', (e) => {
            const { componentId, method, params } = e.detail;
            this.handleAction(componentId, method, e);
        });

        window.addEventListener('popstate', () => {
            this.logger.log('Browser navigation detected, reloading page');
            window.location.reload();
        });
    }

    /**
     * Hydrate all components
     */
    hydrateComponents() {
        const elements = document.querySelectorAll('[diff\\:id]');
        
        elements.forEach(element => {
            if (element.hasAttribute('data-diffyne-lazy')) {
                return; // Skip lazy components
            }

            this.hydrateComponent(element);
        });
    }

    /**
     * Hydrate single component
     */
    hydrateComponent(element) {
        const id = element.getAttribute('diff:id');
        const componentClass = element.getAttribute('diff:class');
        const componentName = element.getAttribute('diff:name');
        const state = parseJSON(element.getAttribute('diff:state') || '{}');
        const fingerprint = element.getAttribute('diff:fingerprint');
        const signature = element.getAttribute('diff:signature');
        const eventListeners = parseJSON(element.getAttribute('diff:listeners') || '{}');

        this.registry.register(id, {
            id,
            componentClass,
            componentName,
            element,
            state,
            fingerprint,
            signature,
            vdom: this.vNodeConverter.buildVDOM(element),
        });
        
        const component = this.registry.get(id);
        if (component) {
            component.serverState = JSON.parse(JSON.stringify(state));
        }

        this.modelSync.sync(element, state);
        this.eventBinder.bind(element, id);
        this.eventManager.bindEventListeners(element, id);

        // Register event listeners from #[On] attributes
        if (eventListeners && Object.keys(eventListeners).length > 0) {
            this.registerServerEventListeners(id, eventListeners);
        }
        
        this.logger.log(`Hydrated component: ${id} (${componentName})`);
    }

    /**
     * Load all lazy components
     */
    loadLazyComponents() {
        const lazyElements = document.querySelectorAll('[data-diffyne-lazy]');
        lazyElements.forEach(element => this.loadLazyComponent(element));
    }

    /**
     * Load single lazy component
     */
    async loadLazyComponent(element) {
        const id = element.getAttribute('diff:id');
        const componentClass = element.getAttribute('diff:class');
        const componentName = element.getAttribute('diff:name');
        const params = parseJSON(element.getAttribute('diff:params') || '{}');
        const queryParams = getQueryParams();

        this.logger.log(`Loading lazy component: ${id} (${componentName})`);

        try {
            const data = await this.transport.loadLazy(
                componentClass, 
                id, 
                params, 
                queryParams
            );

            if (data.success) {
                element.setAttribute('diff:state', JSON.stringify(data.state));
                element.setAttribute('diff:fingerprint', data.fingerprint);
                element.removeAttribute('data-diffyne-lazy');
                element.setAttribute('data-diffyne-loaded', '');
                element.innerHTML = data.html;

                this.registry.register(id, {
                    id,
                    componentClass,
                    componentName,
                    element,
                    state: data.state,
                    fingerprint: data.fingerprint,
                    vdom: this.vNodeConverter.buildVDOM(element),
                });
                
                const lazyComponent = this.registry.get(id);
                if (lazyComponent) {
                    lazyComponent.serverState = JSON.parse(JSON.stringify(data.state));
                }

                this.modelSync.sync(element, data.state);
                this.eventBinder.bind(element, id);
                this.eventManager.bindEventListeners(element, id);

                // Register event listeners from #[On] attributes
                if (data.eventListeners) {
                    this.registerServerEventListeners(id, data.eventListeners);
                }

                this.logger.log(`Lazy component loaded: ${id} (${componentName})`);
            } else {
                element.innerHTML = `<div style="color: red; padding: 1rem;">Failed to load component: ${data.error}</div>`;
            }
        } catch (error) {
            this.logger.error('Error loading lazy component:', error);
            element.innerHTML = `<div style="color: red; padding: 1rem;">Error loading component</div>`;
        }
    }

    /**
     * Handle action (method call)
     */
    async handleAction(componentId, action, event = null) {
        const [method, ...args] = parseAction(action);
        this.logger.log(`Action: ${method}`, args);
        await this.callMethod(componentId, method, args);
    }

    /**
     * Handle model update (with server sync)
     */
    async handleModelUpdate(componentId, property, value) {
        this.logger.log(`Model update: ${property} = ${value}`);
        await this.updateProperty(componentId, property, value);
    }

    /**
     * Update local state only (no server request)
     */
    updateLocalState(componentId, property, value) {
        const component = this.registry.get(componentId);
        if (!component) return;

        // Update local state
        component.state[property] = value;
        
        this.logger.log(`Local state update: ${property} = ${value}`);
    }

    /**
     * Handle file upload
     */
    async handleFileUpload(componentId, property, fileOrFiles, isMultiple = false) {
        const component = this.registry.get(componentId);
        if (!component) return;

        try {
            this.loadingService.show(component.element);
            
            if (isMultiple && Array.isArray(fileOrFiles)) {
                const identifiers = [];
                for (const file of fileOrFiles) {
                    try {
                        const result = await this.fileUpload.uploadFile(file, componentId, property);
                        if (result.success) {
                            identifiers.push(result.identifier);
                        }
                    } catch (error) {
                        this.logger.error(`Failed to upload file ${file.name}:`, error);
                    }
                }
                
                if (identifiers.length > 0) {
                    const currentValue = component.state[property];
                    const existing = Array.isArray(currentValue) ? currentValue : [];
                    await this.updateProperty(componentId, property, [...existing, ...identifiers]);
                }
            } 
            else if (!isMultiple) {
                const result = await this.fileUpload.uploadFile(fileOrFiles, componentId, property);
                
                if (result.success) {
                    const currentValue = component.state[property];
                    if (Array.isArray(currentValue)) {
                        await this.updateProperty(componentId, property, [...currentValue, result.identifier]);
                    } else {
                        await this.updateProperty(componentId, property, result.identifier);
                    }
                }
            }
        } catch (error) {
            this.errorService.display(component.element, {
                [property]: [error.message || 'Upload failed']
            });
        } finally {
            this.loadingService.hide(component.element);
        }
    }

    /**
     * Call component method
     */
    async callMethod(componentId, method, params = []) {
        const component = this.registry.get(componentId);
        if (!component) {
            return;
        }

        // Cancel any pending requests for this component
        this.cancelPendingRequest(componentId);

        this.loadingService.show(component.element);

        const currentState = JSON.parse(JSON.stringify(component.state));

        // Create abort controller for request cancellation
        const abortController = new AbortController();
        const requestId = this.getNextRequestId(componentId);
        
        this.pendingRequests.set(componentId, {
            controller: abortController,
            requestId: requestId
        });

        try {
            const response = await this.transport.send({
                type: 'call',
                componentId,
                componentClass: component.componentClass,
                method,
                params,
                state: currentState,
                fingerprint: component.fingerprint,
                signature: component.signature
            });

            // Check if this request is still valid (not superseded)
            if (this.isRequestValid(componentId, requestId)) {
                this.processResponse(componentId, response, requestId, 'call');
            }
        } catch (error) {
            // Only handle error if request wasn't cancelled
            if (error.name !== 'AbortError' && this.isRequestValid(componentId, requestId)) {
                this.handleError(componentId, error);
            }
        } finally {
            // Only hide loading if this is still the latest request
            if (this.isRequestValid(componentId, requestId)) {
                this.loadingService.hide(component.element);
            }
            this.pendingRequests.delete(componentId);
        }
    }

    /**
     * Update component property
     */
    async updateProperty(componentId, property, value) {
        const component = this.registry.get(componentId);
        if (!component) return;

        // Cancel any pending requests for this component
        this.cancelPendingRequest(componentId);

        const serverState = component.serverState || component.state;
        const serverSignature = component.signature;

        // Store the property being updated so we can preserve local changes for other properties
        const updatingProperty = property;

        // Create abort controller for request cancellation
        const abortController = new AbortController();
        const requestId = this.getNextRequestId(componentId);
        
        this.pendingRequests.set(componentId, {
            controller: abortController,
            requestId: requestId,
            updatingProperty: updatingProperty // Track which property is being updated
        });

        try {
            const response = await this.transport.send({
                type: 'update',
                componentId,
                componentClass: component.componentClass,
                property,
                value,
                state: serverState,
                fingerprint: component.fingerprint,
                signature: serverSignature
            });

            // Check if this request is still valid (not superseded)
            if (this.isRequestValid(componentId, requestId)) {
                this.processResponse(componentId, response, requestId, 'update', updatingProperty);
            }
        } catch (error) {
            // Only handle error if request wasn't cancelled
            if (error.name !== 'AbortError' && this.isRequestValid(componentId, requestId)) {
                this.handleError(componentId, error);
            }
        } finally {
            this.pendingRequests.delete(componentId);
        }
    }

    /**
     * Process server response
     */
    processResponse(componentId, response, requestId = null, requestType = 'call', updatedProperty = null) {
        const component = this.registry.get(componentId);
        if (!component) return;

        // Double-check request is still valid
        if (requestId && !this.isRequestValid(componentId, requestId)) {
            this.logger.log(`Ignoring stale response for ${componentId} (request ${requestId})`);
            return;
        }

        const success = response.s !== undefined ? response.s : response.success;
        
        if (success && response.redirect) {
            const url = response.redirect.url;
            const spa = response.redirect.spa !== undefined ? response.redirect.spa : true;
            
            if (spa) {
                // SPA navigation - fetch and replace content
                this.spaNavigate(url);
            } else {
                // Full page redirect
                window.location.href = url;
            }
            return;
        }
        
        const componentData = response.c || response.component;
        if (!success || !componentData) return;

        const patches = componentData.p || componentData.patches || [];
        const state = componentData.st || componentData.state;
        const fingerprint = componentData.f || componentData.fingerprint;
        const signature = componentData.sig || componentData.signature;
        const errors = componentData.e || componentData.errors;
        const queryString = componentData.q || componentData.queryString;

        this.logger.log(`Applying ${patches.length} patches to ${componentId}`, patches);

        const contentRoot = component.element.firstElementChild;
        if (contentRoot) {
            try {
                this.patchApplier.applyPatches(contentRoot, patches);
            } catch (patchError) {
                this.logger.error(`Failed to apply patches to ${componentId}:`, patchError);
                // Fallback: re-render the component
                this.fallbackRerender(componentId, componentData);
                return;
            }
        }

        if (state) {
            if (requestType === 'update' && updatedProperty) {
                const mergedState = { ...component.state };
                if (state.hasOwnProperty(updatedProperty)) {
                    mergedState[updatedProperty] = state[updatedProperty];
                }
                component.updateState(mergedState);
                component.serverState = JSON.parse(JSON.stringify(state));
                
                const propertyValue = state[updatedProperty];
                if (propertyValue !== undefined) {
                    const modelInputs = this.modelSync.findModelInputs(component.element);
                    modelInputs.forEach(input => {
                        if (input.type === 'file') {
                            return;
                        }
                        
                        const modelAttr = this.modelSync.findModelAttribute(input);
                        if (modelAttr) {
                            const property = input.getAttribute(modelAttr.name);
                            if (property === updatedProperty) {
                                this.modelSync.syncInput(input, propertyValue);
                            }
                        }
                    });
                }
            } else {
                component.updateState(state);
                component.serverState = JSON.parse(JSON.stringify(state));
                this.modelSync.sync(component.element, state);
            }
        }
        
        if (fingerprint) {
            component.updateFingerprint(fingerprint);
        }

        if (signature) {
            component.updateSignature(signature);
        }

        if (queryString) {
            updateQueryString(queryString);
        }

        if (errors) {
            component.setErrors(errors);
            this.errorService.display(component.element, errors);
        } else {
            component.clearErrors();
            this.errorService.clear(component.element);
        }

        // Handle dispatched events
        if (response.events) {
            this.eventManager.dispatchEvents(response.events);
        }

        // Handle browser events
        if (response.browserEvents) {
            this.eventManager.dispatchBrowserEvents(response.browserEvents);
        }
    }

    /**
     * Handle errors
     */
    handleError(componentId, error) {
        const component = this.registry.get(componentId);
        
        // Support both formats: errors at root or nested in details
        const errors = error.details?.errors || error.details?.details?.errors;
        
        this.logger.log('handleError called:', {
            componentId,
            errorType: error.type,
            hasDetails: !!error.details,
            hasErrors: !!errors,
            errors: errors
        });
        
        if (error.type === 'validation_error' && errors) {
            if (component) {
                this.logger.log('Displaying validation errors:', errors);
                component.setErrors(errors);
                this.errorService.display(component.element, errors);
            } else {
                this.logger.error('Component not found for validation errors:', componentId);
            }
        } else {
            this.errorService.handle(error, this.config.debug);
        }
    }

    /**
     * Observe DOM changes
     */
    observeDOMChanges() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        const newComponents = node.querySelectorAll('[diff\\:id]');
                        newComponents.forEach(el => this.hydrateComponent(el));
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Perform SPA navigation to a new URL
     */
    async spaNavigate(url) {
        try {
            this.logger.log('SPA navigation to:', url);

            const response = await fetch(url, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const html = await response.text();
            
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Update page title
            document.title = doc.title;
            
            // Replace body content
            document.body.innerHTML = doc.body.innerHTML;
            
            // Update URL
            window.history.pushState({}, '', url);
            
            // Re-hydrate all components on the new page
            this.registry.clear();
            this.hydrateComponents();
            
            this.logger.log('SPA navigation completed');
        } catch (error) {
            this.logger.error('SPA navigation failed, falling back to full page reload:', error);
            window.location.href = url;
        }
    }

    /**
     * Register event listeners from server-side #[On] attributes
     */
    registerServerEventListeners(componentId, eventListeners) {
        // eventListeners is an object: { 'event-name': ['methodName1', 'methodName2'] }
        for (const [eventName, methods] of Object.entries(eventListeners)) {
            methods.forEach(methodName => {
                this.eventManager.on(componentId, eventName, async (...params) => {
                    // Call the component method when event is triggered
                    this.logger.log(`Event '${eventName}' triggered on component ${componentId}, calling ${methodName}`);
                    await this.callMethod(componentId, methodName, params);
                });
            });
        }
        
        this.logger.log(`Registered ${Object.keys(eventListeners).length} event listeners for component ${componentId}`);
    }

    /**
     * Cancel pending request for a component
     */
    cancelPendingRequest(componentId) {
        const pending = this.pendingRequests.get(componentId);
        if (pending && pending.controller) {
            pending.controller.abort();
            this.pendingRequests.delete(componentId);
            this.logger.log(`Cancelled pending request for ${componentId}`);
        }
    }

    /**
     * Get next request ID for sequencing
     */
    getNextRequestId(componentId) {
        const current = this.requestSequence.get(componentId) || 0;
        const next = current + 1;
        this.requestSequence.set(componentId, next);
        return next;
    }

    /**
     * Check if request is still valid (not superseded)
     */
    isRequestValid(componentId, requestId) {
        const currentSequence = this.requestSequence.get(componentId) || 0;
        return requestId >= currentSequence;
    }

    /**
     * Fallback re-render when patch application fails
     */
    fallbackRerender(componentId, componentData) {
        const component = this.registry.get(componentId);
        if (!component) return;

        this.logger.log(`Fallback: Re-rendering component ${componentId} due to patch failure`);
        
        // For now, just log the error - full re-render would require server support
        // In production, we might want to reload the page or request a full HTML render
        this.logger.error(`Patch application failed for ${componentId}. Consider using keys for list items.`);
    }
}

// Auto-initialize
if (typeof window !== 'undefined') {
    window.Diffyne = Diffyne;
    
    document.addEventListener('DOMContentLoaded', () => {
        const config = window.diffyneConfig || {};
        window.diffyne = new Diffyne(config);
    });
}

export default Diffyne;
