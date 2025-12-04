# Quick Start

Build your first Diffyne component in 5 minutes!

## 1. Generate Component

```bash
php artisan make:diffyne Counter
```

This creates:
- `app/Diffyne/Counter.php` - Component class
- `resources/views/diffyne/counter.blade.php` - Component view

## 2. Component Class

Open `app/Diffyne/Counter.php` and define your component:

```php
<?php

namespace App\Diffyne;

use Diffyne\Attributes\Invokable;
use Diffyne\Component;

class Counter extends Component
{
    public int $count = 0;

    #[Invokable]
    public function increment()
    {
        $this->count++;
    }

    #[Invokable]
    public function decrement()
    {
        $this->count--;
    }
}
```

### Key Points:
- Public properties are **reactive** - changes trigger UI updates
- Methods must be marked with `#[Invokable]` to be called from the browser (security feature)
- No need for manual state management

## 3. Component View

Open `resources/views/diffyne/counter.blade.php`:

```blade
<div class="p-6 max-w-sm mx-auto bg-white rounded-xl shadow-md">
    <div class="text-center">
        <h2 class="text-2xl font-bold mb-4">Counter</h2>
        
        <div class="text-4xl font-bold mb-6">
            {{ $count }}
        </div>
        
        <div class="space-x-4">
            <button 
                diff:click="decrement"
                class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                -
            </button>
            
            <button 
                diff:click="increment"
                class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                +
            </button>
        </div>
    </div>
</div>
```

### Key Concepts:
- `{{ $count }}` displays the current count
- `diff:click="increment"` calls the method on the server
- Changes are automatically synced via Virtual DOM diff

## 4. Use Component

Add the component to any Blade view:

```blade
@diffyne('Counter')
```

## 5. See It In Action

Visit your page and click the buttons. Each click:
1. Sends AJAX request to server
2. Server updates `$count`
3. Re-renders component
4. Computes minimal DOM diff
5. Applies patch to browser (only the changed text node!)

## How It Works

```
User clicks button
    ↓
JavaScript sends AJAX request with method name
    ↓
Server calls increment() method
    ↓
$count property changes
    ↓
Component re-renders to Virtual DOM
    ↓
Diff engine computes minimal patch
    ↓
Patch sent to browser (~50 bytes)
    ↓
DOM updated (only the count text)
```

## What Makes Diffyne Fast?

Instead of sending full HTML like:
```html
<div class="text-4xl font-bold mb-6">1</div>
```

Diffyne sends a minimal patch:
```json
{"type":"text","node":"#text-42","value":"1"}
```

**Result:** 70-95% smaller payloads than traditional approaches.

## Next Steps

- [Your First Component](first-component.md) - Detailed component tutorial
- [Data Binding](../features/data-binding.md) - Two-way data sync
- [Validation](../features/validation.md) - Form validation
- [Examples](../examples/) - More component examples
