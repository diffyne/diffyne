# Your First Component

Let's build a complete todo list component to understand all the core concepts.

## Creating the Component

```bash
php artisan make:diffyne TodoList
```

## Component Structure

### 1. Define Properties and Methods

Edit `app/Diffyne/TodoList.php`:

```php
<?php

namespace App\Diffyne;

use Diffyne\Attributes\Invokable;
use Diffyne\Component;

class TodoList extends Component
{
    // Public properties are reactive
    public array $todos = [];
    public string $newTodo = '';
    
    // Called when component mounts
    public function mount()
    {
        $this->todos = [
            'Learn Diffyne',
            'Build something awesome',
        ];
    }
    
    // Add new todo
    #[Invokable]
    public function addTodo()
    {
        if (trim($this->newTodo) !== '') {
            $this->todos[] = $this->newTodo;
            $this->newTodo = ''; // Clear input
        }
    }
    
    // Remove todo by index
    #[Invokable]
    public function removeTodo($index)
    {
        unset($this->todos[$index]);
        $this->todos = array_values($this->todos); // Re-index
    }
    
    // Mark all complete (example)
    #[Invokable]
    public function clearAll()
    {
        $this->todos = [];
    }
}
```

### 2. Create the View

Edit `resources/views/diffyne/todo-list.blade.php`:

```blade
<div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
    <h2 class="text-2xl font-bold mb-4">My Todo List</h2>
    
    {{-- Add Todo Form --}}
    <form diff:submit="addTodo" class="mb-4">
        <div class="flex gap-2">
            <input 
                type="text"
                diff:model="newTodo"
                placeholder="Add a new todo..."
                class="flex-1 px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <button 
                type="submit"
                class="px-6 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                Add
            </button>
        </div>
    </form>
    
    {{-- Todo List --}}
    @if(count($todos) > 0)
        <ul class="space-y-2 mb-4">
            @foreach($todos as $index => $todo)
                <li class="flex items-center justify-between p-3 bg-gray-50 rounded">
                    <span>{{ $todo }}</span>
                    <button 
                        diff:click="removeTodo({{ $index }})"
                        class="text-red-500 hover:text-red-700">
                        ✕
                    </button>
                </li>
            @endforeach
        </ul>
        
        <button 
            diff:click="clearAll"
            class="w-full py-2 bg-gray-200 rounded hover:bg-gray-300">
            Clear All
        </button>
    @else
        <p class="text-gray-500 text-center py-8">No todos yet. Add one above!</p>
    @endif
</div>
```

## Understanding the Code

### Reactive Properties

```php
public array $todos = [];
public string $newTodo = '';
```

Any public property is automatically reactive. When it changes, the UI updates.

### Lifecycle Hook: mount()

```php
public function mount()
{
    $this->todos = ['Learn Diffyne', 'Build something awesome'];
}
```

`mount()` runs once when the component first loads. Use it for initialization.

### Event Directives

#### diffyne:click
```blade
<button diff:click="removeTodo({{ $index }})">✕</button>
```
Calls server method when clicked.

#### diffyne:submit
```blade
<form diff:submit="addTodo">
```
Handles form submission. Default form submission is automatically prevented.

### Data Binding

```blade
<input diff:model="newTodo">
```

- `diffyne:model` - Two-way binding
- Without modifiers - Updates local state, syncs on change

Modifiers:
- `.live` - Sync with server immediately on input
- `.lazy` - Sync on change event
- `.live.debounce.300` - Sync with server after 300ms of inactivity

## Using the Component

Add to any page:

```blade
@extends('layouts.app')

@section('content')
    @diffyne('TodoList')
@endsection
```

## How Updates Work

When you click "Add":

1. **Browser** sends AJAX request:
   ```json
   {
     "method": "addTodo",
     "state": {
       "todos": ["Learn Diffyne", "Build something awesome"],
       "newTodo": "New task"
     }
   }
   ```

2. **Server** executes:
   - Hydrates component with state
   - Calls `addTodo()` method
   - `$todos` array grows
   - `$newTodo` cleared
   - Re-renders view to Virtual DOM

3. **Diff Engine** compares:
   - Old Virtual DOM vs New Virtual DOM
   - Generates minimal patches

4. **Response** sent:
   ```json
   {
     "s": true,
     "c": {
       "i": "comp-1",
       "p": [
         {"type": "add", "parent": "ul", "html": "<li>...</li>"},
         {"type": "text", "node": "#input-1", "value": ""}
       ],
       "st": {
         "todos": ["Learn Diffyne", "Build...", "New task"],
         "newTodo": ""
       }
     }
   }
   ```

5. **Browser** applies patches:
   - Adds new `<li>` element
   - Clears input field
   - Total update time: ~50-100ms

## Common Patterns

### Passing Parameters

```blade
<button diff:click="removeTodo({{ $index }})">
```

```php
public function removeTodo($index)
{
    unset($this->todos[$index]);
    $this->todos = array_values($this->todos);
}
```

### Loading States

```blade
<button 
    diff:click="addTodo"
    diffyne:loading.class.opacity-50>
    Add
</button>
```

### Conditional Rendering

```blade
@if(count($todos) > 0)
    <ul>...</ul>
@else
    <p>No todos yet!</p>
@endif
```

## Next Steps

- [Data Binding](../features/data-binding.md) - Deep dive into model binding
- [Validation](../features/validation.md) - Add form validation
- [Loading States](../features/loading-states.md) - Better UX during updates
- [Contact Form Example](../examples/contact-form.md) - Form with validation
