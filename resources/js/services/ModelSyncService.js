/**
 * ModelSyncService.js
 * Syncs model-bound inputs with component state (Single Responsibility)
 */

export class ModelSyncService {
    /**
     * Sync all model inputs with state
     */
    sync(element, state) {
        const modelInputs = this.findModelInputs(element);
        
        modelInputs.forEach(input => {
            const modelAttr = this.findModelAttribute(input);
            if (!modelAttr) return;
            
            const property = input.getAttribute(modelAttr.name);
            const modifiers = this.parseModifiers(modelAttr.name, property);
            const propertyName = modifiers.property;
            
            if (state.hasOwnProperty(propertyName)) {
                this.syncInput(input, state[propertyName]);
            }
        });
    }

    /**
     * Find all model-bound inputs
     */
    findModelInputs(element) {
        return Array.from(element.querySelectorAll('*')).filter(el => {
            return Array.from(el.attributes).some(attr => 
                attr.name === 'diff:model' || attr.name.startsWith('diff:model.')
            );
        });
    }

    /**
     * Find model attribute
     */
    findModelAttribute(element) {
        return Array.from(element.attributes).find(attr => 
            attr.name === 'diff:model' || attr.name.startsWith('diff:model.')
        );
    }

    /**
     * Sync single input value
     */
    syncInput(input, value) {
        if (input.tagName === 'INPUT') {
            if (input.type === 'checkbox') {
                // For checkboxes, value is boolean
                const checked = Boolean(value);
                if (input.checked !== checked) {
                    input.checked = checked;
                }
            } else if (input.type === 'radio') {
                // For radio buttons, check if this input's value matches the state value
                const checked = input.value === String(value);
                if (input.checked !== checked) {
                    input.checked = checked;
                }
            } else {
                // For text inputs, textarea, etc.
                const normalizedValue = value ?? '';
                if (input.value !== normalizedValue) {
                    input.value = normalizedValue;
                }
            }
        } else if (input.tagName === 'TEXTAREA') {
            const normalizedValue = value ?? '';
            if (input.value !== normalizedValue) {
                input.value = normalizedValue;
            }
        } else if (input.tagName === 'SELECT') {
            const normalizedValue = value ?? '';
            if (input.value !== normalizedValue) {
                input.value = normalizedValue;
            }
        }
    }

    /**
     * Parse modifiers from directive
     */
    parseModifiers(attrName, property) {
        const parts = attrName.split('.');
        
        return {
            property: property,
            live: parts.includes('live'),
            lazy: parts.includes('lazy'),
        };
    }
}
