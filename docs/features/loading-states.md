# Loading States

Show visual feedback during server requests with `diffyne:loading`.

## Basic Usage

### Add Class During Loading

```blade
<button diffyne:loading.class.opacity-50>
    Save
</button>
```

When the button is clicked, `opacity-50` class is added until the server responds.

### Remove Class During Loading

```blade
<span diffyne:loading.remove.hidden class="hidden">
    <svg class="animate-spin">...</svg>
</span>
```

Perfect for showing hidden elements during loading - the `hidden` class is removed when loading starts.

### Set Attribute During Loading

```blade
<button diffyne:loading.attr.disabled>
    Submit
</button>
```

Sets the `disabled` attribute on the button during loading, preventing duplicate submissions.

### Default Loading Behavior

```blade
<button diffyne:click="submit">
    Submit
    <span diffyne:loading>...</span>
</button>
```

Without modifiers, elements with `diffyne:loading` get default styles: `opacity: 0.5` and `pointer-events: none`.

## Common Patterns

### Button with Spinner

```blade
<button 
    diffyne:click="save"
    diffyne:loading.attr.disabled
    class="bg-blue-500 text-white px-4 py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed">
    <span diffyne:loading.remove.hidden class="hidden mr-2">
        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" 
                    stroke="currentColor" stroke-width="4" fill="none"></circle>
            <path class="opacity-75" fill="currentColor" 
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </span>
    Save
</button>
```

### Loading Overlay

```blade
<div class="relative">
    <form diffyne:submit="submit">
        <input diffyne:model="name">
        <button type="submit">Submit</button>
    </form>
    
    <div diffyne:loading.remove.hidden
         class="absolute inset-0 bg-white bg-opacity-75 hidden flex items-center justify-center">
        <div class="text-center">
            <svg class="animate-spin h-12 w-12 mx-auto mb-2" viewBox="0 0 24 24">
                <!-- Spinner SVG -->
            </svg>
            <p>Processing...</p>
        </div>
    </div>
</div>
```

### Progress Indicator

```blade
<div>
    <button diffyne:click="process">Start Processing</button>
    
    <div diffyne:loading class="mt-4">
        <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div class="bg-blue-600 h-2.5 rounded-full animate-pulse" style="width: 45%"></div>
        </div>
        <p class="text-sm text-gray-600 mt-2">Processing your request...</p>
    </div>
</div>
```

### Skeleton Loaders

```blade
<div>
    {{-- Actual content --}}
    <div>
        @foreach($items as $item)
            <div class="border p-4 mb-2">
                <h3>{{ $item->title }}</h3>
                <p>{{ $item->description }}</p>
            </div>
        @endforeach
    </div>
    
    {{-- Loading skeleton (shown with default opacity/pointer-events) --}}
    <div diffyne:loading>
        @for($i = 0; $i < 3; $i++)
            <div class="border p-4 mb-2 animate-pulse">
                <div class="h-6 bg-gray-300 rounded mb-2"></div>
                <div class="h-4 bg-gray-300 rounded w-3/4"></div>
            </div>
        @endfor
    </div>
</div>
```

## Modifiers

### .class.{className}

Adds CSS class during loading:

```blade
<button diffyne:loading.class.opacity-50>
<button diffyne:loading.class.disabled>
```

The specified class is added when loading starts and removed when loading ends.

### .remove.{className}

Removes CSS class during loading (re-adds when done):

```blade
<span diffyne:loading.remove.hidden class="hidden">
    <svg class="animate-spin">...</svg>
</span>
```

Perfect for showing hidden elements during loading. The class is removed when loading starts and restored when loading completes.

### .attr.{attrName}

Sets an attribute during loading (empty value):

```blade
<button diffyne:loading.attr.disabled>Submit</button>
```

Original attribute values are preserved and restored after loading.

### .attr.{attrName}.{value}

Sets an attribute with a specific value during loading:

```blade
<button diffyne:loading.attr.aria-busy.true>Process</button>
```

Useful for setting ARIA attributes or other attributes that need specific values.

### No Modifier (Default Styles)

Without modifiers, elements get default opacity and pointer-events:

```blade
<div diffyne:loading>
    Visible only when loading
</div>
```

Elements get `opacity: 0.5` and `pointer-events: none` automatically.

## Important Notes

- Each element can have **one** `diffyne:loading` attribute with its modifiers
- The attribute name itself contains the modifiers (e.g., `diffyne:loading.remove.hidden`)
- Original attribute values are automatically preserved and restored
- Classes are added/removed atomically for smooth transitions

## Multiple Loading States

### Different Actions

```blade
<div>
    <button 
        diffyne:click="save"
        diffyne:loading.class.opacity-50>
        Save
    </button>
    
    <button 
        diffyne:click="delete"
        diffyne:loading.class.opacity-50>
        Delete
    </button>
    
    {{-- Shows during ANY action --}}
    <div diffyne:loading>Processing...</div>
</div>
```

### Form with Multiple Buttons

```blade
<form diffyne:submit="submit">
    <input diffyne:model="name">
    
    <button 
        type="submit"
        diffyne:loading.class.opacity-50>
        Submit
    </button>
    
    <button 
        type="button"
        diffyne:click="saveDraft"
        diffyne:loading.class.opacity-50>
        Save Draft
    </button>
    
    {{-- Shows during submit or saveDraft --}}
    <span diffyne:loading class="text-blue-500">Processing...</span>
</form>
```

## Advanced Patterns

### Complete Form with All Loading Features

```blade
<form diffyne:submit="submit" class="relative">
    {{-- Loading overlay using .remove.hidden --}}
    <div diffyne:loading.remove.hidden
         class="absolute inset-0 bg-white bg-opacity-75 hidden flex items-center justify-center rounded-lg z-10">
        <div class="text-center">
            <svg class="animate-spin h-10 w-10 text-blue-500 mx-auto mb-2" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p>Processing your request...</p>
        </div>
    </div>
    
    <input diffyne:model="name" class="mb-4">
    <input diffyne:model="email" class="mb-4">
    
    {{-- Button with disabled attribute and spinner --}}
    <button
        type="submit"
        diffyne:loading.attr.disabled
        class="bg-blue-500 text-white px-6 py-3 rounded disabled:opacity-50 disabled:cursor-not-allowed">
        <span diffyne:loading.remove.hidden class="hidden mr-2">
            <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </span>
        Submit
    </button>
</form>
```

### Disabled Form During Loading

```blade
<form diffyne:submit="submit">
    <div diffyne:loading.class.opacity-50>
        <input diffyne:model="name">
        <input diffyne:model="email">
        <textarea diffyne:model="message"></textarea>
        
        <button type="submit">Submit</button>
    </div>
    
    <div diffyne:loading.remove.hidden class="hidden text-blue-500 mt-2">
        Submitting your form...
    </div>
</form>
```

### Loading State with Animation

```blade
<style>
.loading-dots::after {
    content: '';
    animation: dots 1.5s infinite;
}

@keyframes dots {
    0%, 20% { content: ''; }
    40% { content: '.'; }
    60% { content: '..'; }
    80%, 100% { content: '...'; }
}
</style>

<button diffyne:click="process">
    <span diffyne:loading.remove>Process</span>
    <span diffyne:loading class="loading-dots">Processing</span>
</button>
```

### Context-Specific Loading Messages

```blade
<div>
    <button diffyne:click="sendEmail">Send Email</button>
    <button diffyne:click="generateReport">Generate Report</button>
    <button diffyne:click="exportData">Export Data</button>
    
    <div diffyne:loading class="mt-4 p-4 bg-blue-100 rounded">
        <p class="font-semibold">Processing your request</p>
        <p class="text-sm text-gray-600">This may take a few moments...</p>
    </div>
</div>
```

## Styling Loading States

### Tailwind CSS Examples

```blade
{{-- Opacity --}}
<button diffyne:loading.class="opacity-50">

{{-- Cursor --}}
<button diffyne:loading.class="cursor-wait">

{{-- Background --}}
<button diffyne:loading.class="bg-gray-400">

{{-- Multiple classes --}}
<button diffyne:loading.class="opacity-50 cursor-not-allowed scale-95">

{{-- With transitions --}}
<button class="transition-all" diffyne:loading.class="opacity-50 scale-95">
```

### Custom CSS

```css
.btn-loading {
    position: relative;
    pointer-events: none;
}

.btn-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 16px;
    height: 16px;
    margin: -8px 0 0 -8px;
    border: 2px solid white;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
```

Usage:

```blade
<button diffyne:loading.class="btn-loading">
    Submit
</button>
```

## Best Practices

### 1. Always Disable Buttons During Loading

```blade
<button 
    diffyne:click="save"
    diffyne:loading.attr.disabled
    class="disabled:opacity-50 disabled:cursor-not-allowed">
    Save
</button>
```

Prevents multiple clicks and duplicate requests.

### 2. Provide Visual Feedback

```blade
{{-- Good - clear feedback --}}
<button 
    diffyne:loading.attr.disabled
    class="disabled:opacity-50">
    <span diffyne:loading.remove.hidden class="hidden">
        <svg class="animate-spin">...</svg>
    </span>
    Save
</button>

{{-- Avoid - no feedback --}}
<button diffyne:click="save">Save</button>
```

### 3. Use Appropriate Loading Messages

```blade
{{-- Context-specific messages --}}
<span diffyne:loading>Sending email...</span>
<span diffyne:loading>Generating PDF...</span>
<span diffyne:loading>Uploading file...</span>
```

### 4. Consider Skeleton Loaders for Content

Better UX than spinners for content areas:

```blade
<div diffyne:loading.remove>
    {{ $content }}
</div>

<div diffyne:loading>
    {{-- Skeleton matching content structure --}}
    <div class="animate-pulse">
        <div class="h-4 bg-gray-300 rounded mb-2"></div>
        <div class="h-4 bg-gray-300 rounded w-5/6"></div>
    </div>
</div>
```

### 5. Combine with Form Validation

```blade
<form diffyne:submit.prevent="submit">
    <input diffyne:model.defer="email">
    <span diffyne:error="email"></span>
    
    <button 
        type="submit"
        diffyne:loading.class="opacity-50"
        diffyne:loading.attr="disabled">
        <span diffyne:loading.remove>Submit</span>
        <span diffyne:loading>Submitting...</span>
    </button>
</form>
```

## Troubleshooting

### Loading State Not Showing

Ensure you're triggering a server request:

```blade
{{-- This triggers server request - loading works --}}
<button diffyne:click="save">

{{-- This doesn't - loading won't trigger --}}
<button onclick="console.log('hi')">
```

### Loading State Stuck

Check for JavaScript errors in console. Server must respond for loading to clear.

### Multiple Loading Indicators

`diffyne:loading` shows for ANY action in the component. Use conditional logic if you need action-specific indicators.

## Next Steps

- [Click Events](click-events.md) - Trigger actions
- [Forms](forms.md) - Form handling
- [Polling](polling.md) - Real-time updates
- [Examples](../examples/) - See loading states in action
