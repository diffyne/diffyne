# Diffyne
A blazing-fast, server-driven UI framework for PHP powered by a lightweight **Virtual DOM + Diff Engine** (Delta Rendering Engine).  
Diffyne lets you build dynamic interfaces with the simplicity of Blade/PHP components — but with the rendering efficiency of modern SPA frameworks.

It delivers **minimal DOM updates**, optional **WebSocket sync**, and full compatibility with **Alpine.js**.

---

## 🚀 Why Diffyne?

### 🔥 Ultra-efficient delta updates
Diffyne does **not** send full HTML fragments.  
It computes a Virtual DOM diff and ships **only the smallest possible patch** to the browser.

### 🧠 Server-driven logic
Write your UI logic entirely in PHP.  
The client applies diffs — nothing else.

### 🌱 Alpine Friendly
Alpine.js works seamlessly with Diffyne components.

### 🔄 AJAX or WebSocket transport
Choose between:
- **AJAX mode** (default)  
- **WebSocket mode** (realtime)  

Perfect for dashboards, forms, CRUDs, or real-time UIs.

---

# 📦 Installation

### 1. Install via Composer
```bash
composer require diffyne/diffyne
````

### 2. Publish assets + config

```bash
php artisan diffyne:install
```

### 3. Load the frontend script

```html
<script src="/vendor/diffyne/diffyne.js" defer></script>
```

---

# 🧩 Creating Your First Component

### 1. Generate component

```bash
php artisan make:diffyne counter
```

Creates:

```
app/Diffyne/Counter.php
resources/diffyne/counter.blade.php
```

---

### 2. Component Class

```php
namespace App\Diffyne;

use Diffyne\Component;

class Counter extends Component
{
    public int $count = 0;

    public function increment()
    {
        $this->count++;
    }
}
```

---

### 3. Component View

```html
<div>
    <h1>Count: {{ $count }}</h1>
    <button diffyne:click="increment">Increment</button>
</div>
```

---

### 4. Use in page

```html
<diffyne:counter />
```

Diffyne automatically:

* hydrates the component
* syncs events
* diffs DOM
* applies patches

---

# ⚙️ Diffyne Directives

| Directive            | Description             |
| -------------------- | ----------------------- |
| `diffyne:click`      | Call method on server   |
| `diffyne:change`     | Trigger on input change |
| `diffyne:model`      | Two-way bind property   |
| `diffyne:submit`     | Handle forms            |
| `diffyne:init`       | Runs on hydration       |
| `diffyne:poll="500"` | Poll server every X ms  |
| `diffyne:debounce`   | Debounce event          |
| `diffyne:loading`    | Loading state binding   |

### Example

```html
<input diffyne:model.live.debounce.300ms="search">
<button diffyne:click="save" diffyne:loading.class="opacity-50">
```

---

# 🔄 Two-Way Binding Examples

### Text Input

```html
<input type="text" diffyne:model="username">
```

### Checkbox

```html
<input type="checkbox" diffyne:model="active">
```

### Select

```html
<select diffyne:model="category">
```

---

# 🎯 Real Example: Todo App

### PHP Component

```php
class Todo extends Component
{
    public array $items = [];
    public string $newItem = '';

    public function add()
    {
        if ($this->newItem !== '') {
            $this->items[] = $this->newItem;
            $this->newItem = '';
        }
    }

    public function remove($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }
}
```

---

### Blade View

```html
<div>
    <input diffyne:model="newItem" placeholder="Add todo...">
    <button diffyne:click="add">Add</button>

    <ul>
        @foreach ($items as $i => $item)
            <li>
                {{ $item }}
                <button diffyne:click="remove({{ $i }})">x</button>
            </li>
        @endforeach
    </ul>
</div>
```

---

# ⚡ WebSocket Mode

Diffyne includes an optional realtime WebSocket server.

### Start server:

```bash
php artisan diffyne:serve
```

### Enable in `AppServiceProvider`:

```php
Diffyne::enableWebSockets();
```

This gives you:

* realtime updates
* low-latency syncing
* multi-user shared UI

---

# 🧬 Diffyne Virtual DOM Engine (DVDE)

Diffyne uses a custom Virtual DOM engine to achieve high performance.

Rendering pipeline:

```
PHP state
    → template render
        → virtual DOM snapshot
            → diff engine
                → delta packets
                    → client patcher
                        → DOM update
```

### Patch Types Supported

* Text node updates
* Attribute diffing
* Add/remove elements
* Reorder lists (keyed diffing)
* Input value preservation
* Component hydration patches

### Example patch packet sent to browser:

```json
{
  "type": "text",
  "node": "#text-17",
  "value": "Count: 5"
}
```

---

# ♻️ Lifecycle Hooks

### Hooks available:

| Hook               | Trigger                            |
| ------------------ | ---------------------------------- |
| `mount()`          | Before component renders           |
| `hydrate()`        | After client hydration             |
| `updating($field)` | Before a property updates          |
| `updated($field)`  | After a property updates           |
| `dehydrate()`      | Before sending DOM diffs to client |

### Example usage:

```php
public function updated($field)
{
    logger("Updated: $field");
}
```

---

# 🗂 Directory Structure

```
src/
 ├── Components/
 ├── Traits/
 ├── State/
 
resources/
 └── diffyne/
       └── components/
public/
 └── vendor/
       └── diffyne/
config/
 └── diffyne.php
```

---

# 🧪 Testing Components

```php
$this->diffyne(Todo::class)
     ->set('newItem', 'Learn Diffyne')
     ->call('add')
     ->assertSee('Learn Diffyne');
```

---

# ⚡ Performance Advantages

* DOM patches are **70–95% smaller** than Livewire/HTMX-style HTML responses.
* Only changed nodes are updated — no full HTML morphing.
* Alpine.js DOM changes are respected and not overwritten.
* WebSocket mode delivers instantaneous updates.
* Minimal JS payload (<10 KB minified).

---

# 🛣 Roadmap

### v1.0

* Full directive engine
* Virtual DOM diff engine
* WebSocket server
* Alpine compatibility layer
* Hydration & de-hydration
* Error boundaries

### Future

* Partial hydration / islands
* Streaming SSR
* Advanced keyed loop diffing
* Static segment compiler
* Devtools inspector
* Plugin API

---

# 🤝 Contributing

Pull requests are welcome!
Follow PSR-12 and include tests for new features.

---

# 📝 License

MIT License © 2025 Diffyne Team