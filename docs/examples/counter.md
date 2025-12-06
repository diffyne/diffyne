# Counter Component Example

A simple counter to demonstrate basic Diffyne concepts.

## Component Code

### PHP Class

`app/Diffyne/Counter.php`:

```php
<?php

namespace App\Diffyne;

use Diffyne\Component;

class Counter extends Component
{
    public int $count = 0;
    
    public function increment()
    {
        $this->count++;
    }
    
    public function decrement()
    {
        if ($this->count > 0) {
            $this->count--;
        }
    }
    
    public function reset()
    {
        $this->count = 0;
    }
}
```

### Blade View

`resources/views/diffyne/counter.blade.php`:

```blade
<div class="p-6 max-w-sm mx-auto bg-white rounded-xl shadow-md">
    <div class="text-center">
        <h2 class="text-2xl font-bold mb-4">Counter</h2>
        
        <div class="text-6xl font-bold mb-6 text-blue-600">
            {{ $count }}
        </div>
        
        <div class="flex justify-center gap-4 mb-4">
            <button 
                diff:click="decrement"
                diff:loading.class.opacity-50
                class="px-6 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                -
            </button>
            
            <button 
                diff:click="increment"
                diff:loading.class.opacity-50
                class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">
                +
            </button>
        </div>
        
        <button 
            diff:click="reset"
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition">
            Reset
        </button>
    </div>
</div>
```

### Usage

```blade
@diffyne('Counter')
```

## How It Works

### 1. Public Property

```php
public int $count = 0;
```

This property is:
- **Reactive** - Changes trigger UI updates
- **Type-safe** - Ensures it's always an integer
- **Hydrated** - Synced between server and client

### 2. Public Methods

```php
public function increment()
{
    $this->count++;
}
```

Public methods can be called from the browser using `diff:click`.

### 3. Event Binding

```blade
<button diff:click="increment">+</button>
```

When clicked:
1. Browser sends AJAX request to server
2. Server calls `increment()` method
3. `$count` increases
4. Component re-renders
5. Virtual DOM diff computed
6. Minimal patch sent to browser
7. UI updates (only the count number changes)

### 4. Loading States

```blade
<button 
    diff:click="increment"
    diff:loading.class.opacity-50>
    +
</button>
```

While the server processes the request:
- Button becomes semi-transparent (`opacity-50` class added)
- Default behavior also applies `pointer-events: none`

## Data Flow

```
User clicks "+" button
    ↓
Browser: diffyne.callMethod('increment')
    ↓
AJAX request to server: {method: 'increment', state: {count: 0}}
    ↓
Server: Counter component hydrated with state
    ↓
Server: increment() method called
    ↓
Server: $count becomes 1
    ↓
Server: View re-rendered to Virtual DOM
    ↓
Server: Diff engine computes changes
    ↓
Response: {patches: [{type: 'text', node: '#count', value: '1'}], state: {count: 1}}
    ↓
Browser: Applies patch (updates text node)
    ↓
UI: Count displays "1"
```

## Optimization

The counter sends only ~50 bytes per update:

```json
{
  "type": "text",
  "node": "#count-text",
  "value": "1"
}
```

Compare to full HTML approach (~200 bytes):

```html
<div class="text-6xl font-bold mb-6 text-blue-600">1</div>
```

**Result:** 75% smaller payload!

## Enhancements

### Add Step Size

```php
public int $count = 0;
public int $step = 1;

public function increment()
{
    $this->count += $this->step;
}

public function decrement()
{
    $this->count -= $this->step;
    if ($this->count < 0) {
        $this->count = 0;
    }
}
```

```blade
<input type="number" diff:model.live="step" min="1" max="10">
<button diff:click="increment">+ {{ $step }}</button>
```

### Add Limits

```php
public int $count = 0;
public int $min = 0;
public int $max = 100;

public function increment()
{
    if ($this->count < $this->max) {
        $this->count++;
    }
}

public function decrement()
{
    if ($this->count > $this->min) {
        $this->count--;
    }
}
```

```blade
<p class="text-sm text-gray-600">Range: {{ $min }} - {{ $max }}</p>
```

### Add Animation

```blade
<style>
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.count-update {
    animation: pulse 0.3s;
}
</style>

<div class="text-6xl font-bold mb-6 text-blue-600 count-update">
    {{ $count }}
</div>
```

## Next Steps

- [Todo List Example](todo-list.md) - Working with arrays
- [Contact Form Example](contact-form.md) - Forms with validation
- [Click Events](../features/click-events.md) - More about event handling
- [Loading States](../features/loading-states.md) - Better UX
