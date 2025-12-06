/**
 * EventManager.js
 * Manages component-to-component event dispatching and browser events
 */

export class EventManager {
    constructor(registry, logger) {
        this.registry = registry;
        this.logger = logger;
        this.listeners = new Map(); // eventName => [{componentId, handler}]
    }

    /**
     * Register event listener for a component
     */
    on(componentId, eventName, handler) {
        if (!this.listeners.has(eventName)) {
            this.listeners.set(eventName, []);
        }

        this.listeners.get(eventName).push({
            componentId,
            handler
        });

        this.logger.log(`Registered listener for '${eventName}' on component ${componentId}`);
    }

    /**
     * Dispatch events from server response
     */
    dispatchEvents(events) {
        if (!events || events.length === 0) return;

        events.forEach(event => {
            const { name, params, to, self } = event;

            this.logger.log(`Dispatching event '${name}'`, { params, to, self });

            const listeners = this.listeners.get(name) || [];

            listeners.forEach(listener => {
                // If event is targeted to specific components
                if (to && to.length > 0) {
                    if (!to.includes(listener.componentId)) {
                        return; // Skip this listener
                    }
                }

                try {
                    listener.handler(...params);
                } catch (error) {
                    this.logger.error(`Error in event listener for '${name}':`, error);
                }
            });
        });
    }

    /**
     * Dispatch browser events (JavaScript custom events)
     */
    dispatchBrowserEvents(events) {
        if (!events || events.length === 0) return;

        events.forEach(event => {
            const { name, data } = event;

            this.logger.log(`Dispatching browser event '${name}'`, data);

            const customEvent = new CustomEvent(name, {
                detail: data,
                bubbles: true,
                cancelable: true
            });

            window.dispatchEvent(customEvent);
        });
    }

    /**
     * Bind event listeners from diff:on directives
     */
    bindEventListeners(element, componentId) {
        const eventElements = element.querySelectorAll('[diff\\:on]');

        eventElements.forEach(el => {
            Array.from(el.attributes).forEach(attr => {
                if (attr.name.startsWith('diff:on.')) {
                    const eventName = attr.name.substring(8); // Remove 'diff:on.'
                    const method = attr.value;

                    // Register the listener
                    this.on(componentId, eventName, (...params) => {
                        this.logger.log(`Event '${eventName}' received by component ${componentId}, calling ${method}`);
                        
                        // Trigger the component method
                        const component = this.registry.get(componentId);
                        if (component && component.element) {
                            // Dispatch a custom event on the element to trigger the method call
                            const actionEvent = new CustomEvent('diff:action', {
                                detail: { componentId, method, params }
                            });
                            component.element.dispatchEvent(actionEvent);
                        }
                    });

                    this.logger.log(`Bound event listener: ${eventName} -> ${method} on component ${componentId}`);
                }
            });
        });
    }

    /**
     * Remove all listeners for a component
     */
    removeListenersForComponent(componentId) {
        this.listeners.forEach((listeners, eventName) => {
            const filtered = listeners.filter(l => l.componentId !== componentId);
            if (filtered.length > 0) {
                this.listeners.set(eventName, filtered);
            } else {
                this.listeners.delete(eventName);
            }
        });
    }

    /**
     * Clear all event listeners
     */
    clear() {
        this.listeners.clear();
    }
}
