# Diffyne

A blazing-fast, server-driven UI framework for PHP powered by a lightweight **Virtual DOM + Diff Engine** (Delta Rendering Engine).  
Diffyne lets you build dynamic interfaces with the simplicity of Blade/PHP components — but with the rendering efficiency of modern SPA frameworks.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E10.0%20%7C%20%5E11.0%20%7C%20%5E12.0-red)](https://laravel.com)

## 🚀 Why Diffyne?

- **🔥 Ultra-efficient:** 70-95% smaller payloads than traditional approaches
- **🧠 Server-driven:** Write UI logic in PHP, not JavaScript
- **⚡ Lightweight:** ~12 KB JS (4 KB gzipped), sub-100ms response times
- **✅ Laravel Native:** Full validation, authentication, and ORM integration

## 📦 Quick Start

```bash
# Install via Composer
composer require diffyne/diffyne

# Publish assets
php artisan vendor:publish --tag=diffyne-assets

# Add to your layout before </body>
@diffyneScripts

# Create your first component
php artisan make:diffyne Counter
```

**[📖 Full Installation Guide →](docs/getting-started/installation.md)**

## 🧩 Example Component

**Component Class** (`app/Diffyne/Counter.php`):
```php
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
}
```

**Component View** (`resources/views/diffyne/counter.blade.php`):
```blade
<div>
    <h1>Count: {{ $count }}</h1>
    <button diff:click="increment">Increment</button>
</div>
```

**Usage:**
```blade
@diffyne('Counter')
```

When the button is clicked, Diffyne sends only the minimal patch (~50 bytes) instead of the full HTML (~400 bytes)!

**[🎯 More Examples →](docs/examples/)**

## ⚙️ Core Features

| Directive | Description |
|-----------|-------------|
| `diffyne:click` | Call server method on click |
| `diffyne:model` | Two-way data binding |
| `diffyne:submit` | Handle form submission |
| `diffyne:poll` | Auto-refresh at intervals |
| `diffyne:loading` | Show loading states |
| `diffyne:error` | Display validation errors |

```blade
{{-- Live search with debouncing --}}
<input diffyne:model.live.debounce.300="search">

{{-- Form with validation --}}
<form diff:submit="submit">
    <input diff:model="email">
    <span diff:error="email"></span>
    <button type="submit" diffyne:loading.class.opacity-50>Submit</button>
</form>
```

**[📚 Complete Directives Guide →](docs/features/directives.md)**

## 📖 Documentation

### Getting Started
- [Installation](docs/getting-started/installation.md)
- [Quick Start](docs/getting-started/quickstart.md)
- [Your First Component](docs/getting-started/first-component.md)

### Features
- [Directives Overview](docs/features/directives.md)
- [Click Events](docs/features/click-events.md)
- [Data Binding](docs/features/data-binding.md)
- [Forms](docs/features/forms.md)
- [Validation](docs/features/validation.md)
- [Loading States](docs/features/loading-states.md)
- [Polling](docs/features/polling.md)
- [Error Handling](docs/features/error-handling.md)

### Examples
- [Counter Component](docs/examples/counter.md)
- [Todo List](docs/examples/todo-list.md)
- [Contact Form](docs/examples/contact-form.md)
- [Live Search](docs/examples/search.md)

### Advanced
- [Virtual DOM Engine](docs/advanced/virtual-dom.md)
- [Lifecycle Hooks](docs/advanced/lifecycle-hooks.md)
- [Component State](docs/advanced/component-state.md)
- [Performance](docs/advanced/performance.md)
- [Testing](docs/advanced/testing.md)

## ⚡ Performance

- **70-95% smaller payloads** than traditional HTML-over-the-wire approaches
- **Sub-100ms response times** for most operations
- **~12 KB minified JS** (~4 KB gzipped)
- Only changed DOM nodes are updated
- Automatic input value syncing
- Built-in validation with automatic error display

## 🛣 Roadmap

**v1.0 (Current)**
- ✅ Virtual DOM diff engine
- ✅ Core directives & data binding
- ✅ Form validation
- ✅ Lifecycle hooks
- ✅ Loading states & polling
- ✅ Component events (dispatch, dispatchTo, dispatchSelf)
- ✅ Query string binding
- ✅ WebSocket support (via Sockeon)
- ✅ Security features (state signing, locked properties)
- ✅ Attributes (Locked, QueryString, On, Invokable, Computed, Lazy)
- ✅ Redirects (SPA navigation)
- ✅ Browser events

**Coming Soon**
- File uploads
- Flash messages
- Nested components

## 🤝 Contributing

Pull requests are welcome! Follow PSR-12 and include tests for new features.

## 📝 License

MIT License © 2025 Diffyne Team