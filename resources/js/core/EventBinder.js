/**
 * EventBinder.js
 * Handles event binding and delegation (Single Responsibility)
 */

import { debounce } from '../utils/helpers.js';

export class EventBinder {
    constructor(actionHandler, modelHandler, localStateHandler) {
        this.actionHandler = actionHandler;
        this.modelHandler = modelHandler;
        this.localStateHandler = localStateHandler;
    }

    /**
     * Bind all events for a component using delegation
     */
    bind(element, componentId) {
        if (element.hasAttribute('data-diffyne-delegated')) {
            return;
        }
        element.setAttribute('data-diffyne-delegated', 'true');

        this.bindClickEvents(element, componentId);
        this.bindChangeEvents(element, componentId);
        this.bindModelEvents(element, componentId);
        this.bindSubmitEvents(element, componentId);
        this.bindPollEvents(element, componentId);
    }

    /**
     * Bind click events
     */
    bindClickEvents(wrapper, componentId) {
        wrapper.addEventListener('click', (e) => {
            const target = e.target.closest('[diff\\:click]');
            if (target && wrapper.contains(target)) {
                const action = target.getAttribute('diff:click');
                this.actionHandler(componentId, action, e);
            }
        });
    }

    /**
     * Bind change events
     */
    bindChangeEvents(wrapper, componentId) {
        wrapper.addEventListener('change', (e) => {
            const target = e.target.closest('[diff\\:change]');
            if (target && wrapper.contains(target)) {
                const action = target.getAttribute('diff:change');
                this.actionHandler(componentId, action, e);
            }
        });
    }

    /**
     * Bind model events (input/change)
     */
    bindModelEvents(wrapper, componentId) {
        // Helper to get the correct value based on input type
        const getInputValue = (input) => {
            if (input.type === 'checkbox') {
                return input.checked;
            } else if (input.type === 'radio') {
                return input.checked ? input.value : undefined;
            } else {
                return input.value;
            }
        };

        // Input events
        wrapper.addEventListener('input', (e) => {
            const target = e.target;
            const modelAttr = this.findModelAttribute(target);
            
            if (modelAttr) {
                const property = target.getAttribute(modelAttr.name);
                const modifiers = this.parseModifiers(modelAttr.name, property);
                const value = getInputValue(target);
                
                if (modifiers.live) {
                    if (!target._diffyneModelHandler) {
                        let handler = (val) => this.modelHandler(componentId, modifiers.property, val);
                        
                        if (modifiers.debounce) {
                            handler = debounce(handler, modifiers.debounce);
                        }
                        
                        target._diffyneModelHandler = handler;
                    }
                    
                    target._diffyneModelHandler(value);
                } else if (target.tagName !== 'SELECT' && 
                           target.type !== 'checkbox' && 
                           target.type !== 'radio') {
                    // Only update local state for text inputs that won't trigger server request on change
                    // SELECT, checkbox, radio always trigger server requests, so don't update local state
                    this.localStateHandler(componentId, modifiers.property, value);
                }
            }
        });

        // Change events
        wrapper.addEventListener('change', (e) => {
            const target = e.target;
            const modelAttr = this.findModelAttribute(target);
            
            if (modelAttr) {
                const property = target.getAttribute(modelAttr.name);
                const modifiers = this.parseModifiers(modelAttr.name, property);
                const value = getInputValue(target);
                
                // For radio buttons, only send update if checked
                if (target.type === 'radio' && !target.checked) {
                    return;
                }
                
                if (modifiers.lazy || target.tagName === 'SELECT' || 
                    target.type === 'checkbox' || target.type === 'radio') {
                    this.modelHandler(componentId, modifiers.property, value);
                } else if (!modifiers.live) {
                    // Update local state for non-live inputs on change
                    this.localStateHandler(componentId, modifiers.property, value);
                }
            }
        });
    }

    /**
     * Bind submit events
     */
    bindSubmitEvents(wrapper, componentId) {
        wrapper.addEventListener('submit', (e) => {
            const target = e.target.closest('[diff\\:submit]');
            if (target && wrapper.contains(target)) {
                e.preventDefault();
                const action = target.getAttribute('diff:submit');
                this.actionHandler(componentId, action, e);
            }
        });
    }

    /**
     * Bind poll events
     */
    bindPollEvents(wrapper, componentId) {
        wrapper.querySelectorAll('[diff\\:poll]').forEach(el => {
            if (el.hasAttribute('data-diffyne-poll-bound')) return;
            el.setAttribute('data-diffyne-poll-bound', 'true');
            
            const interval = parseInt(el.getAttribute('diff:poll')) || 2000;
            const action = el.getAttribute('diff:poll.action') || 'refresh';
            
            setInterval(() => {
                this.actionHandler(componentId, action);
            }, interval);
        });
    }

    /**
     * Find model attribute on element
     */
    findModelAttribute(element) {
        return Array.from(element.attributes).find(attr => 
            attr.name === 'diff:model' || attr.name.startsWith('diff:model.')
        );
    }

    /**
     * Parse modifiers from directive
     */
    parseModifiers(attrName, property) {
        const parts = attrName.split('.');
        
        const mods = {
            property: property,
            live: parts.includes('live'),
            lazy: parts.includes('lazy'),
            debounce: null
        };

        const debounceIdx = parts.findIndex(p => p === 'debounce');
        if (debounceIdx >= 0 && parts[debounceIdx + 1]) {
            mods.debounce = parseInt(parts[debounceIdx + 1]);
        } else if (mods.live) {
            mods.debounce = 150;
        }

        return mods;
    }
}
