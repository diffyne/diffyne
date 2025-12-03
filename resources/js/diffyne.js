/**
 * Diffyne - Client-side Virtual DOM Patcher
 * Handles component hydration, event binding, and DOM patching
 */

class Diffyne {
    constructor(config = {}) {
        this.config = {
            transport: config.transport || 'ajax',
            wsUrl: config.wsUrl || 'ws://localhost:8080/diffyne',
            endpoint: config.endpoint || '/_diffyne',
            debug: config.debug || false,
            ...config
        };

        this.components = new Map();
        this.ws = null;
        this.requestQueue = [];
        this.processing = false;

        this.init();
    }

    /**
     * Initialize Diffyne
     */
    init() {
        this.log('Initializing Diffyne...');

        if (this.config.transport === 'websocket') {
            this.connectWebSocket();
        }

        // Hydrate all components on page load
        this.hydrateComponents();

        // Set up mutation observer for dynamic components
        this.observeDOMChanges();

        // Handle browser back/forward navigation
        window.addEventListener('popstate', () => {
            this.log('Browser navigation detected, reloading page');
            window.location.reload();
        });
    }

    /**
     * Hydrate all Diffyne components in the DOM
     */
    hydrateComponents() {
        const elements = document.querySelectorAll('[diff\\:id]');
        
        elements.forEach(element => {
            const id = element.getAttribute('diff:id');
            const componentClass = element.getAttribute('diff:class');
            const componentName = element.getAttribute('diff:name');
            const state = this.parseJSON(element.getAttribute('diff:state') || '{}');
            const fingerprint = element.getAttribute('diff:fingerprint');

            this.components.set(id, {
                id,
                componentClass,
                componentName,
                element,
                state,
                fingerprint,
                vdom: this.buildVDOM(element),
                errors: {},
            });

            // Sync model inputs with initial state
            this.syncModelInputs(element, state);

            this.bindEvents(element, id);
            this.log(`Hydrated component: ${id} (${componentName})`);
        });
    }

    /**
     * Build Virtual DOM representation from actual DOM
     */
    buildVDOM(element) {
        // Simplified VDOM for client-side tracking
        return {
            tag: element.tagName.toLowerCase(),
            attrs: this.getAttributes(element),
            children: Array.from(element.childNodes).map(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    return { type: 'text', value: node.textContent };
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    return this.buildVDOM(node);
                }
                return null;
            }).filter(Boolean)
        };
    }

    /**
     * Get all attributes from an element
     */
    getAttributes(element) {
        const attrs = {};
        Array.from(element.attributes).forEach(attr => {
            attrs[attr.name] = attr.value;
        });
        return attrs;
    }

    /**
     * Bind event listeners for diffyne: directives using event delegation
     */
    bindEvents(element, componentId) {
        // Use event delegation on the wrapper element
        const wrapper = element;
        
        // Check if already delegated
        if (wrapper.hasAttribute('data-diffyne-delegated')) {
            return;
        }
        wrapper.setAttribute('data-diffyne-delegated', 'true');

        // Click events (delegated)
        wrapper.addEventListener('click', (e) => {
            const target = e.target.closest('[diff\\:click]');
            if (target && wrapper.contains(target)) {
                const action = target.getAttribute('diff:click');
                this.handleAction(componentId, action, e);
            }
        });

        // Change events (delegated)
        wrapper.addEventListener('change', (e) => {
            const target = e.target.closest('[diff\\:change]');
            if (target && wrapper.contains(target)) {
                const action = target.getAttribute('diff:change');
                this.handleAction(componentId, action, e);
            }
        });

        // Model binding (delegated on input/change)
        wrapper.addEventListener('input', (e) => {
            const target = e.target;
            
            // Find the diffyne:model attribute (could be diffyne:model, diffyne:model.live, etc.)
            const modelAttr = Array.from(target.attributes).find(attr => 
                attr.name === 'diff:model' || attr.name.startsWith('diff:model.')
            );
            
            if (modelAttr) {
                const property = target.getAttribute(modelAttr.name);
                const modifiers = this.parseModifiers(modelAttr.name, property);
                
                // Only send requests if .live modifier is present
                if (modifiers.live) {
                    // Store the handler on the element to maintain debounce state
                    if (!target._diffyneModelHandler) {
                        let handler = (value) => this.handleModelUpdate(componentId, modifiers.property, value);
                        
                        if (modifiers.debounce) {
                            handler = this.debounce(handler, modifiers.debounce);
                        }
                        
                        target._diffyneModelHandler = handler;
                    }
                    
                    target._diffyneModelHandler(target.value);
                } else {
                    // Just update local state without sending request
                    this.updateLocalState(componentId, modifiers.property, target.value);
                }
            }
        });

        wrapper.addEventListener('change', (e) => {
            const target = e.target;
            
            const modelAttr = Array.from(target.attributes).find(attr => 
                attr.name === 'diff:model' || attr.name.startsWith('diff:model.')
            );
            
            if (modelAttr) {
                const property = target.getAttribute(modelAttr.name);
                const modifiers = this.parseModifiers(modelAttr.name, property);
                
                // For lazy updates or select/checkbox/radio
                if (modifiers.lazy || target.tagName === 'SELECT' || target.type === 'checkbox' || target.type === 'radio') {
                    this.handleModelUpdate(componentId, modifiers.property, target.value);
                } else if (!modifiers.live) {
                    // Update local state for non-live inputs on change
                    this.updateLocalState(componentId, modifiers.property, target.value);
                }
            }
        });

        // Submit events (delegated)
        wrapper.addEventListener('submit', (e) => {
            const target = e.target.closest('[diff\\:submit]');
            if (target && wrapper.contains(target)) {
                e.preventDefault();
                const action = target.getAttribute('diff:submit');
                this.handleAction(componentId, action, e);
            }
        });

        // Poll directives (set up once on hydration)
        wrapper.querySelectorAll('[diff\\:poll]').forEach(el => {
            if (el.hasAttribute('data-diffyne-poll-bound')) return;
            el.setAttribute('data-diffyne-poll-bound', 'true');
            
            const interval = parseInt(el.getAttribute('diff:poll')) || 2000;
            const action = el.getAttribute('diff:poll.action') || 'refresh';
            
            setInterval(() => {
                this.handleAction(componentId, action);
            }, interval);
        });
    }

    /**
     * Parse modifiers from directive
     * @param {string} attrName - The attribute name (e.g., "diff:model.live.debounce.500")
     * @param {string} property - The property name from attribute value (e.g., "user.name")
     */
    parseModifiers(attrName, property) {
        // Parse modifiers from attribute name (e.g., diff:model.live.debounce.500)
        const parts = attrName.split('.');
        
        const mods = {
            property: property, // Use the attribute value as the property name
            live: parts.includes('live'),
            lazy: parts.includes('lazy'),
            debounce: null
        };

        // Extract debounce value
        const debounceIdx = parts.findIndex(p => p === 'debounce');
        if (debounceIdx >= 0 && parts[debounceIdx + 1]) {
            mods.debounce = parseInt(parts[debounceIdx + 1]);
        } else if (mods.live) {
            mods.debounce = 150; // Default debounce for live updates
        }

        return mods;
    }

    /**
     * Get the appropriate event type for model binding
     */
    getModelEventType(element, modifiers) {
        if (modifiers.lazy) return 'change';
        
        const tag = element.tagName.toLowerCase();
        const type = element.type;

        if (tag === 'select' || type === 'checkbox' || type === 'radio') {
            return 'change';
        }

        return 'input';
    }

    /**
     * Handle action (method call)
     */
    async handleAction(componentId, action, event = null) {
        const [method, ...args] = this.parseAction(action);
        
        this.log(`Action: ${method}`, args);

        await this.callMethod(componentId, method, args);
    }

    /**
     * Handle model property update (with server sync)
     */
    async handleModelUpdate(componentId, property, value) {
        this.log(`Model update: ${property} = ${value}`);

        await this.updateProperty(componentId, property, value);
    }

    /**
     * Update local state only (no server request)
     */
    updateLocalState(componentId, property, value) {
        const component = this.components.get(componentId);
        if (!component) return;

        // Update local state
        component.state[property] = value;
        
        this.log(`Local state update: ${property} = ${value}`);
    }

    /**
     * Parse action string to extract method and parameters
     */
    parseAction(action) {
        const match = action.match(/^(\w+)(?:\((.*)\))?$/);
        
        if (!match) return [action];

        const method = match[1];
        const paramsStr = match[2];

        if (!paramsStr) return [method];

        // Parse parameters (basic implementation)
        const params = paramsStr.split(',').map(p => {
            p = p.trim();
            // Handle strings
            if (p.startsWith("'") || p.startsWith('"')) {
                return p.slice(1, -1);
            }
            // Handle numbers
            if (!isNaN(p)) return Number(p);
            // Handle booleans
            if (p === 'true') return true;
            if (p === 'false') return false;
            return p;
        });

        return [method, ...params];
    }

    /**
     * Call a component method
     */
    async callMethod(componentId, method, params = []) {
        const component = this.components.get(componentId);
        if (!component) return;

        this.showLoading(component.element);

        try {
            const response = await this.request({
                type: 'call',
                componentId,
                componentClass: component.componentClass,
                componentName: component.componentName,
                method,
                params,
                state: component.state,
                fingerprint: component.fingerprint,
                errors: component.errors || {}
            });

            this.applyPatches(componentId, response);
        } catch (error) {
            // Handle validation errors specially
            if (error.type === 'validation_error' && error.details?.errors) {
                component.errors = error.details.errors;
                this.displayErrors(component.element, error.details.errors);
            } else {
                this.handleError(error);
            }
        } finally {
            this.hideLoading(component.element);
        }
    }

    /**
     * Update a component property
     */
    async updateProperty(componentId, property, value) {
        const component = this.components.get(componentId);
        if (!component) return;

        // Optimistic update
        component.state[property] = value;

        try {
            const response = await this.request({
                type: 'update',
                componentId,
                componentClass: component.componentClass,
                componentName: component.componentName,
                property,
                value,
                state: component.state,
                fingerprint: component.fingerprint,
                errors: component.errors || {}
            });

            this.applyPatches(componentId, response);
        } catch (error) {
            if (error.type === 'validation_error' && error.details?.errors) {
                component.errors = error.details.errors;
                this.displayErrors(component.element, error.details.errors);
            } else {
                this.handleError(error);
            }
        }
    }

    /**
     * Send request to server
     */
    async request(payload) {
        if (this.config.transport === 'websocket') {
            return this.sendWebSocket(payload);
        } else {
            return this.sendAjax(payload);
        }
    }

    /**
     * Send AJAX request
     */
    async sendAjax(payload) {
        const response = await fetch(this.config.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.getCsrfToken()
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok || !data.s) {
            const error = new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
            error.type = data.type;
            error.details = data;
            throw error;
        }

        return data;
    }

    /**
     * Send WebSocket message
     */
    sendWebSocket(payload) {
        return new Promise((resolve, reject) => {
            if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
                reject(new Error('WebSocket not connected'));
                return;
            }

            const requestId = this.generateId();
            payload.requestId = requestId;

            // Set up one-time listener for response
            const handler = (event) => {
                const response = JSON.parse(event.data);
                if (response.requestId === requestId) {
                    this.ws.removeEventListener('message', handler);
                    resolve(response);
                }
            };

            this.ws.addEventListener('message', handler);
            this.ws.send(JSON.stringify(payload));

            // Timeout after 30 seconds
            setTimeout(() => {
                this.ws.removeEventListener('message', handler);
                reject(new Error('Request timeout'));
            }, 30000);
        });
    }

    /**
     * Connect to WebSocket server
     */
    connectWebSocket() {
        this.log('Connecting to WebSocket...');

        this.ws = new WebSocket(this.config.wsUrl);

        this.ws.onopen = () => {
            this.log('WebSocket connected');
        };

        this.ws.onclose = () => {
            this.log('WebSocket disconnected');
            // Reconnect after 3 seconds
            setTimeout(() => this.connectWebSocket(), 3000);
        };

        this.ws.onerror = (error) => {
            this.log('WebSocket error:', error);
        };
    }

    /**
     * Apply patches to the DOM
     */
    applyPatches(componentId, response) {
        const component = this.components.get(componentId);
        if (!component) return;

        // Support both minified and full response formats
        const success = response.s !== undefined ? response.s : response.success;
        const componentData = response.c || response.component;
        
        if (!success || !componentData) {
            this.log('Invalid response format');
            return;
        }

        const patches = componentData.p || componentData.patches || [];
        const state = componentData.st || componentData.state;
        const fingerprint = componentData.f || componentData.fingerprint;
        const errors = componentData.e || componentData.errors;
        const queryString = componentData.q || componentData.queryString;

        this.log(`Applying ${patches.length} patches to ${componentId}`, patches);

        const contentRoot = component.element.firstElementChild;
        if (!contentRoot) {
            this.log('No content root found in component wrapper');
            return;
        }

        // Check if this is a full content replacement (CREATE patch at root with empty path)
        if (patches.length === 1 && 
            (patches[0].t === 'c' || patches[0].type === 'create') && 
            (patches[0].p?.length === 0 || patches[0].path?.length === 0)) {
            // Replace entire content
            const patch = patches[0];
            const data = patch.d || patch.data;
            const newContent = this.vnodeToDOM(data.node);
            component.element.replaceChild(newContent, contentRoot);
        } else {
            // Apply individual patches
            patches.forEach(patch => {
                this.applyPatch(contentRoot, patch);
            });
        }

        // Update component metadata only if state is provided
        if (state) {
            component.state = state;
            component.element.setAttribute('diff:state', JSON.stringify(state));
            
            // Sync input values with state for model-bound inputs
            this.syncModelInputs(component.element, state);
        }
        
        if (fingerprint) {
            component.fingerprint = fingerprint;
            component.element.setAttribute('diff:fingerprint', fingerprint);
        }

        // Update URL query string if provided
        if (queryString) {
            this.updateQueryString(queryString);
        }

        if (errors) {
            component.errors = errors;
            this.displayErrors(component.element, errors);
        } else {
            component.errors = {};
            this.clearErrors(component.element);
        }
    }

    /**
     * Display validation errors in the component
     */
    displayErrors(element, errors) {
        this.clearErrors(element);

        Object.entries(errors).forEach(([field, messages]) => {
            const fieldElement = element.querySelector(`[name="${field}"], [diff\\:model="${field}"]`);
            
            if (fieldElement) {
                fieldElement.classList.add('diffyne-error');
                fieldElement.setAttribute('aria-invalid', 'true');

                const errorDisplay = element.querySelector(`[diff\\:error="${field}"]`);
                
                if (errorDisplay) {
                    errorDisplay.textContent = Array.isArray(messages) ? messages[0] : messages;
                    errorDisplay.style.display = '';
                } else {
                    const errorEl = document.createElement('span');
                    errorEl.className = 'diffyne-error-message';
                    errorEl.textContent = Array.isArray(messages) ? messages[0] : messages;
                    errorEl.style.color = '#ef4444';
                    errorEl.style.fontSize = '0.875rem';
                    errorEl.style.marginTop = '0.25rem';
                    
                    if (fieldElement.parentNode) {
                        fieldElement.parentNode.insertBefore(errorEl, fieldElement.nextSibling);
                    }
                }
            }
        });
    }

    /**
     * Clear validation errors from the component
     */
    clearErrors(element) {
        // Remove error classes
        element.querySelectorAll('.diffyne-error').forEach(el => {
            el.classList.remove('diffyne-error');
            el.removeAttribute('aria-invalid');
        });

        // Clear error displays
        element.querySelectorAll('[diff\\:error]').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });

        // Remove dynamically created error messages
        element.querySelectorAll('.diffyne-error-message').forEach(el => {
            el.remove();
        });
    }

    /**
     * Sync model-bound input values with component state
     */
    syncModelInputs(element, state) {
        const modelInputs = Array.from(element.querySelectorAll('*')).filter(el => {
            return Array.from(el.attributes).some(attr => 
                attr.name === 'diff:model' || attr.name.startsWith('diff:model.')
            );
        });
        
        modelInputs.forEach(input => {
            const modelAttr = Array.from(input.attributes).find(attr => 
                attr.name === 'diff:model' || attr.name.startsWith('diff:model.')
            );
            
            if (!modelAttr) return;
            
            const property = input.getAttribute(modelAttr.name);
            const modifiers = this.parseModifiers(modelAttr.name, property);
            const propertyName = modifiers.property;
            
            if (state.hasOwnProperty(propertyName)) {
                const value = state[propertyName] ?? '';
                
                if (input.tagName === 'INPUT' || input.tagName === 'TEXTAREA') {
                    if (input.value !== value) {
                        input.value = value;
                    }
                } else if (input.tagName === 'SELECT') {
                    if (input.value !== value) {
                        input.value = value;
                    }
                }
            }
        });
    }

    /**
     * Apply a single patch to the DOM
     */
    applyPatch(root, patch) {
        // Support both full and minified formats
        const type = patch.t || patch.type;
        const path = patch.p || patch.path;
        const data = patch.d || patch.data;
        
        // Expand minified type
        const fullType = this.expandType(type);
        
        // For CREATE patches, the path includes the index where to insert
        // So we need to navigate to the parent and get the insert index
        let target, insertIndex;
        
        if (fullType === 'create' && path.length > 0) {
            // Last element is the insert position, rest is parent path
            const parentPath = path.slice(0, -1);
            insertIndex = path[path.length - 1];
            target = this.getNodeByPath(root, parentPath);
        } else {
            target = this.getNodeByPath(root, path);
            insertIndex = null;
        }

        if (!target) {
            this.log('Target node not found for path:', path);
            return;
        }

        switch (fullType) {
            case 'create':
                this.patchCreate(target, data, insertIndex);
                break;
            case 'remove':
                this.patchRemove(target);
                break;
            case 'replace':
                this.patchReplace(target, data);
                break;
            case 'update_text':
                this.patchUpdateText(target, data);
                break;
            case 'update_attrs':
                this.patchUpdateAttrs(target, data);
                break;
            case 'reorder':
                this.patchReorder(target, data);
                break;
        }
    }

    /**
     * Expand minified patch type to full name
     */
    expandType(type) {
        const typeMap = {
            'c': 'create',
            'r': 'remove',
            'R': 'replace',
            't': 'update_text',
            'a': 'update_attrs',
            'o': 'reorder'
        };
        return typeMap[type] || type;
    }

    /**
     * Get DOM node by path array (skipping whitespace-only text nodes)
     */
    getNodeByPath(root, path) {
        let node = root;

        for (const index of path) {
            if (!node) return null;
            
            // Get non-whitespace children only
            const meaningfulChildren = Array.from(node.childNodes).filter(child => {
                if (child.nodeType === Node.TEXT_NODE) {
                    return child.textContent.trim() !== '';
                }
                return true;
            });
            
            if (!meaningfulChildren[index]) {
                return null;
            }
            
            node = meaningfulChildren[index];
        }

        return node;
    }

    /**
     * Patch: Create new node
     */
    patchCreate(parent, data, insertIndex = null) {
        const newNode = this.vnodeToDOM(data.node);
        
        if (insertIndex !== null) {
            // Get meaningful children (skip whitespace-only text nodes)
            const meaningfulChildren = Array.from(parent.childNodes).filter(child => {
                if (child.nodeType === Node.TEXT_NODE) {
                    return child.textContent.trim() !== '';
                }
                return true;
            });
            
            const referenceNode = meaningfulChildren[insertIndex];
            if (referenceNode) {
                parent.insertBefore(newNode, referenceNode);
            } else {
                parent.appendChild(newNode);
            }
        } else {
            parent.appendChild(newNode);
        }
    }

    /**
     * Patch: Remove node
     */
    patchRemove(node) {
        node.parentNode?.removeChild(node);
    }

    /**
     * Patch: Replace node
     */
    patchReplace(oldNode, data) {
        const newNode = this.vnodeToDOM(data.node);
        oldNode.parentNode?.replaceChild(newNode, oldNode);
    }

    /**
     * Patch: Update text content
     */
    patchUpdateText(node, data) {
        if (node.nodeType === Node.TEXT_NODE) {
            // Support both minified (x) and full (text) format
            node.textContent = data.x || data.text;
        }
    }

    /**
     * Patch: Update attributes
     */
    patchUpdateAttrs(element, data) {
        // Support both minified and full format
        const setAttrs = data.s || data.set || {};
        const removeAttrs = data.r || data.remove || [];
        
        // Set/update attributes
        Object.entries(setAttrs).forEach(([key, value]) => {
            element.setAttribute(key, value);
            
            // Special handling for form elements - also update the property
            if ((element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') && key === 'value') {
                element.value = value;
            }
        });

        // Remove attributes
        removeAttrs.forEach(key => {
            element.removeAttribute(key);
            
            // Special handling for form elements
            if ((element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') && key === 'value') {
                element.value = '';
            }
        });
    }

    /**
     * Patch: Reorder children
     */
    patchReorder(parent, data) {
        // Simple implementation: reorder keyed children
        data.moves.forEach(move => {
            const node = parent.childNodes[move.from];
            if (node) {
                parent.insertBefore(node, parent.childNodes[move.to]);
            }
        });
    }

    /**
     * Convert VNode to actual DOM node (supports both full and minimal formats)
     */
    vnodeToDOM(vnode) {
        // Minimal format: {x: "text"} or {t: "tag", a: {}, c: []}
        if (vnode.x !== undefined) {
            return document.createTextNode(vnode.x);
        }
        
        if (vnode.m !== undefined) {
            return document.createComment(vnode.m);
        }
        
        if (vnode.t) {
            const element = document.createElement(vnode.t);
            
            // Set attributes (minimal: 'a', full: 'attributes')
            const attrs = vnode.a || vnode.attributes || {};
            Object.entries(attrs).forEach(([key, value]) => {
                element.setAttribute(key, value);
            });
            
            // Add children (minimal: 'c', full: 'children')
            const children = vnode.c || vnode.children || [];
            children.forEach(child => {
                element.appendChild(this.vnodeToDOM(child));
            });
            
            return element;
        }
        
        // Old format fallback
        if (vnode.type === 'text') {
            return document.createTextNode(vnode.text);
        }

        if (vnode.type === 'element') {
            const element = document.createElement(vnode.tag);

            // Set attributes
            Object.entries(vnode.attributes || {}).forEach(([key, value]) => {
                element.setAttribute(key, value);
            });

            // Add children
            (vnode.children || []).forEach(child => {
                element.appendChild(this.vnodeToDOM(child));
            });

            return element;
        }

        return document.createTextNode('');
    }

    /**
     * Show loading state
     * Supports modifiers:
     * - diff:loading (default: opacity + pointer-events)
     * - diff:loading.class.{className} (add class)
     * - diff:loading.remove.{className} (remove class)
     * - diff:loading.attr.{attrName} (set attribute to empty string)
     * - diff:loading.attr.{attrName}.{value} (set attribute with value)
     */
    showLoading(element) {
        const loadingElements = Array.from(element.querySelectorAll('*')).filter(el => {
            return Array.from(el.attributes).some(attr => attr.name.startsWith('diff:loading'));
        });
        
        loadingElements.forEach(el => {
            const loadingAttr = Array.from(el.attributes).find(attr => attr.name.startsWith('diff:loading'));
            if (!loadingAttr) return;
            
            const directive = loadingAttr.value;
            const attrName = loadingAttr.name;
            const parts = attrName.split('.');
            
            if (parts.includes('class')) {
                const classIndex = parts.indexOf('class');
                if (parts[classIndex + 1]) {
                    el.classList.add(parts[classIndex + 1]);
                }
            } else if (parts.includes('remove')) {
                const removeIndex = parts.indexOf('remove');
                if (parts[removeIndex + 1]) {
                    el.classList.remove(parts[removeIndex + 1]);
                }
            } else if (parts.includes('attr')) {
                const attrIndex = parts.indexOf('attr');
                if (parts[attrIndex + 1]) {
                    const attrName = parts[attrIndex + 1];
                    const attrValue = parts[attrIndex + 2] || '';
                    
                    if (!el.hasAttribute('data-diffyne-loading-original-' + attrName)) {
                        const originalValue = el.getAttribute(attrName);
                        if (originalValue !== null) {
                            el.setAttribute('data-diffyne-loading-original-' + attrName, originalValue);
                        }
                    }
                    
                    el.setAttribute(attrName, attrValue);
                }
            } else {
                el.style.opacity = '0.5';
                el.style.pointerEvents = 'none';
            }
        });
    }

    /**
     * Hide loading state
     */
    hideLoading(element) {
        const loadingElements = Array.from(element.querySelectorAll('*')).filter(el => {
            return Array.from(el.attributes).some(attr => attr.name.startsWith('diff:loading'));
        });
        
        loadingElements.forEach(el => {
            const loadingAttr = Array.from(el.attributes).find(attr => attr.name.startsWith('diff:loading'));
            if (!loadingAttr) return;
            
            const directive = loadingAttr.value;
            const attrName = loadingAttr.name;
            const parts = attrName.split('.');
            
            if (parts.includes('class')) {
                const classIndex = parts.indexOf('class');
                if (parts[classIndex + 1]) {
                    el.classList.remove(parts[classIndex + 1]);
                }
            } else if (parts.includes('remove')) {
                const removeIndex = parts.indexOf('remove');
                if (parts[removeIndex + 1]) {
                    el.classList.add(parts[removeIndex + 1]);
                }
            } else if (parts.includes('attr')) {
                const attrIndex = parts.indexOf('attr');
                if (parts[attrIndex + 1]) {
                    const attrName = parts[attrIndex + 1];
                    const originalAttr = 'data-diffyne-loading-original-' + attrName;
                    
                    if (el.hasAttribute(originalAttr)) {
                        const originalValue = el.getAttribute(originalAttr);
                        el.setAttribute(attrName, originalValue);
                        el.removeAttribute(originalAttr);
                    } else {
                        el.removeAttribute(attrName);
                    }
                }
            } else {
                el.style.opacity = '';
                el.style.pointerEvents = '';
            }
        });
    }

    /**
     * Observe DOM changes for dynamically added components
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
     * Helper: Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    /**
     * Helper: Parse JSON safely
     */
    parseJSON(str) {
        try {
            return JSON.parse(str);
        } catch {
            return {};
        }
    }

    /**
     * Helper: Get CSRF token
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Update URL query string
     */
    updateQueryString(params) {
        const url = new URL(window.location);
        
        // Clear existing params and set new ones
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== '' && params[key] !== undefined) {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        
        // Update URL without page reload
        window.history.pushState({}, '', url);
        
        this.log('Updated URL query string:', params);
    }

    /**
     * Helper: Generate random ID
     */
    generateId() {
        return 'req-' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Helper: Log debug messages
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[Diffyne]', ...args);
        }
    }
    /**
     * Handle errors
     */
    handleError(error) {
        console.error('[Diffyne Error]', error);
        
        // Show user-friendly error message
        let message = 'An error occurred';
        
        if (error.type === 'validation_error' && error.details?.errors) {
            // Don't show alert for validation errors, they're shown inline
            return;
        } else if (error.type === 'method_error') {
            message = `Method Error: ${error.message}`;
        } else if (error.type === 'property_error') {
            message = `Property Error: ${error.message}`;
        } else if (error.type === 'exception' && error.details) {
            message = `${error.details.exception}: ${error.message}\nFile: ${error.details.file}:${error.details.line}`;
        } else {
            message = error.message || 'Unknown error occurred';
        }
        
        // Show in console
        console.error('[Diffyne Error Details]', {
            type: error.type,
            message: error.message,
            details: error.details
        });
        
        // You can also show a user-facing notification here
        if (this.config.debug) {
            alert(message);
        }
    }
}

// Auto-initialize if config is present
if (typeof window !== 'undefined') {
    window.Diffyne = Diffyne;
    
    document.addEventListener('DOMContentLoaded', () => {
        const config = window.diffyneConfig || {};
        window.diffyne = new Diffyne(config);
    });
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = Diffyne;
}
