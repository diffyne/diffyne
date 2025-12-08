/**
 * Component.js
 * Represents a single Diffyne component instance
 */

export class Component {
    constructor(data) {
        this.id = data.id;
        this.componentClass = data.componentClass;
        this.componentName = data.componentName;
        this.element = data.element;
        this.state = data.state || {};
        this.serverState = JSON.parse(JSON.stringify(data.state || {}));
        this.fingerprint = data.fingerprint;
        this.signature = data.signature;
        this.vdom = data.vdom;
        this.errors = {};
    }

    /**
     * Update component state
     */
    updateState(newState) {
        this.state = { ...this.state, ...newState };
        this.element.setAttribute('diff:state', JSON.stringify(this.state));
    }

    /**
     * Update fingerprint
     */
    updateFingerprint(fingerprint) {
        this.fingerprint = fingerprint;
        this.element.setAttribute('diff:fingerprint', fingerprint);
    }

    /**
     * Update signature
     */
    updateSignature(signature) {
        this.signature = signature;
        this.element.setAttribute('diff:signature', signature);
    }

    /**
     * Set errors
     */
    setErrors(errors) {
        this.errors = errors || {};
    }

    /**
     * Clear errors
     */
    clearErrors() {
        this.errors = {};
    }

    /**
     * Check if component is lazy
     */
    isLazy() {
        return this.element.hasAttribute('data-diffyne-lazy');
    }

    /**
     * Mark as loaded
     */
    markAsLoaded() {
        this.element.removeAttribute('data-diffyne-lazy');
        this.element.setAttribute('data-diffyne-loaded', '');
    }
}
