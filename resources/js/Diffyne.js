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
import { parseJSON, parseAction, updateQueryString, getQueryParams, Logger } from './utils/helpers.js';

export class Diffyne {
    constructor(config = {}) {
        this.config = {
            transport: config.transport || 'ajax',
            wsUrl: config.wsUrl || 'ws://localhost:8080/diffyne',
            endpoint: config.endpoint || '/_diffyne',
            debug: config.debug || false,
            ...config
        };

        // Initialize services (Dependency Injection)
        this.registry = new ComponentRegistry();
        this.transport = new TransportService(this.config, this.logger);
        this.patchApplier = new PatchApplier();
        this.vNodeConverter = new VNodeConverter();
        this.loadingService = new LoadingService();
        this.errorService = new ErrorService();
        this.modelSync = new ModelSyncService();
        this.logger = new Logger(this.config.debug);
        this.eventManager = new EventManager(this.registry, this.logger);
        
        // Initialize event binder with handlers
        this.eventBinder = new EventBinder(
            (id, action, event) => this.handleAction(id, action, event),
            (id, property, value) => this.handleModelUpdate(id, property, value),
            (id, property, value) => this.updateLocalState(id, property, value)
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
        document.addEventListener('diffyne:action', (e) => {
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
        const eventListeners = parseJSON(element.getAttribute('diff:listeners') || '{}');

        this.registry.register(id, {
            id,
            componentClass,
            componentName,
            element,
            state,
            fingerprint,
            vdom: this.vNodeConverter.buildVDOM(element),
        });

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
     * Call component method
     */
    async callMethod(componentId, method, params = []) {
        const component = this.registry.get(componentId);
        if (!component) {
            return;
        }

        this.loadingService.show(component.element);

        try {
            const response = await this.transport.send({
                type: 'call',
                componentId,
                componentClass: component.componentClass,
                method,
                params,
                state: component.state,
                fingerprint: component.fingerprint
            });

            this.processResponse(componentId, response);
        } catch (error) {
            this.handleError(componentId, error);
        } finally {
            this.loadingService.hide(component.element);
        }
    }

    /**
     * Update component property
     */
    async updateProperty(componentId, property, value) {
        const component = this.registry.get(componentId);
        if (!component) return;

        // Optimistic update
        component.updateState({ [property]: value });

        try {
            const response = await this.transport.send({
                type: 'update',
                componentId,
                componentClass: component.componentClass,
                property,
                value,
                state: component.state,
                fingerprint: component.fingerprint
            });

            this.processResponse(componentId, response);
        } catch (error) {
            this.handleError(componentId, error);
        }
    }

    /**
     * Process server response
     */
    processResponse(componentId, response) {
        const component = this.registry.get(componentId);
        if (!component) return;

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
        const errors = componentData.e || componentData.errors;
        const queryString = componentData.q || componentData.queryString;

        this.logger.log(`Applying ${patches.length} patches to ${componentId}`, patches);

        const contentRoot = component.element.firstElementChild;
        if (contentRoot) {
            this.patchApplier.applyPatches(contentRoot, patches);
        }

        if (state) {
            component.updateState(state);
            this.modelSync.sync(component.element, state);
        }
        
        if (fingerprint) {
            component.updateFingerprint(fingerprint);
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
