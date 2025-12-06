# Todo List Example

An interactive todo list demonstrating array manipulation and form handling.

## Component Code

### PHP Class

`app/Diffyne/TodoList.php`:

```php
<?php

namespace App\Diffyne;

use Diffyne\Attributes\Invokable;
use Diffyne\Component;

class TodoList extends Component
{
    public array $todos = [];
    public string $newTodo = '';
    
    public function mount()
    {
        $this->todos = [
            ['text' => 'Learn Diffyne', 'completed' => false],
            ['text' => 'Build something awesome', 'completed' => false],
        ];
    }
    
    #[Invokable]
    public function addTodo()
    {
        if (trim($this->newTodo) !== '') {
            $this->todos[] = [
                'text' => $this->newTodo,
                'completed' => false,
            ];
            $this->newTodo = '';
        }
    }
    
    #[Invokable]
    public function removeTodo($index)
    {
        unset($this->todos[$index]);
        $this->todos = array_values($this->todos);
    }
    
    #[Invokable]
    public function toggleTodo($index)
    {
        $this->todos[$index]['completed'] = !$this->todos[$index]['completed'];
    }
    
    #[Invokable]
    public function clearCompleted()
    {
        $this->todos = array_values(
            array_filter($this->todos, fn($todo) => !$todo['completed'])
        );
    }
}
```

### Blade View

`resources/views/diffyne/todo-list.blade.php`:

```blade
<div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
    <h2 class="text-2xl font-bold mb-4">My Todo List</h2>
    
    {{-- Add Todo Form --}}
    <form diff:submit.prevent="addTodo" class="mb-4">
        <div class="flex gap-2">
            <input 
                type="text"
                diff:model.defer="newTodo"
                placeholder="Add a new todo..."
                class="flex-1 px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <button 
                type="submit"
                diff:loading.class="opacity-50"
                diff:loading.attr="disabled"
                class="px-6 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                Add
            </button>
        </div>
    </form>
    
    {{-- Todo List --}}
    @if(count($todos) > 0)
        <ul class="space-y-2 mb-4">
            @foreach($todos as $index => $todo)
                <li class="flex items-center justify-between p-3 bg-gray-50 rounded hover:bg-gray-100 transition">
                    <div class="flex items-center flex-1">
                        <input 
                            type="checkbox"
                            @if($todo['completed']) checked @endif
                            diff:change="toggleTodo({{ $index }})"
                            class="mr-3 w-5 h-5 cursor-pointer">
                        <span class="{{ $todo['completed'] ? 'line-through text-gray-500' : '' }}">
                            {{ $todo['text'] }}
                        </span>
                    </div>
                    <button 
                        diff:click="removeTodo({{ $index }})"
                        class="text-red-500 hover:text-red-700 font-bold">
                        ✕
                    </button>
                </li>
            @endforeach
        </ul>
        
        <div class="flex justify-between items-center pt-4 border-t">
            <span class="text-sm text-gray-600">
                {{ count(array_filter($todos, fn($t) => !$t['completed'])) }} remaining
            </span>
            
            @if(count(array_filter($todos, fn($t) => $t['completed'])) > 0)
                <button 
                    diff:click="clearCompleted"
                    class="text-sm text-red-500 hover:text-red-700">
                    Clear completed
                </button>
            @endif
        </div>
    @else
        <p class="text-gray-500 text-center py-8">No todos yet. Add one above!</p>
    @endif
</div>
```

### Usage

```blade
@diffyne('TodoList')
```

## How It Works

### 1. Array Property

```php
public array $todos = [];
```

Arrays are fully reactive in Diffyne. Adding, removing, or modifying items triggers UI updates.

### 2. Lifecycle Hook: mount()

```php
public function mount()
{
    $this->todos = [
        ['text' => 'Learn Diffyne', 'completed' => false],
        ['text' => 'Build something awesome', 'completed' => false],
    ];
}
```

`mount()` runs once when the component first loads. Perfect for initialization.

### 3. Form Submission

```blade
<form diff:submit.prevent="addTodo">
```

- `diff:submit` - Handles form submission
- `.prevent` - Prevents page reload

### 4. Deferred Binding

```blade
<input diff:model.defer="newTodo">
```

`.defer` means the input only syncs when the form is submitted, not on every keystroke. This reduces server requests.

### 5. Passing Parameters

```blade
<button diff:click="removeTodo({{ $index }})">✕</button>
```

You can pass parameters to methods. Here we pass the todo index.

### 6. Conditional Rendering

```blade
@if(count($todos) > 0)
    {{-- Show todo list --}}
@else
    <p>No todos yet.</p>
@endif
```

Use Blade directives for conditional rendering.

## Data Flow

### Adding a Todo

```
User types "Buy milk" and clicks Add
    ↓
Browser: Form submission captured
    ↓
AJAX request: {method: 'addTodo', state: {newTodo: 'Buy milk', todos: [...]}}
    ↓
Server: TodoList component hydrated
    ↓
Server: addTodo() method called
    ↓
Server: New todo added to array
    ↓
Server: $newTodo cleared
    ↓
Server: View re-rendered
    ↓
Server: Diff engine identifies new <li> element
    ↓
Response: [{type: 'add', parent: 'ul', html: '<li>...</li>'}, {type: 'attr', node: 'input', attr: 'value', value: ''}]
    ↓
Browser: New <li> inserted, input cleared
    ↓
UI: Todo appears in list
```

## Key Concepts

### Re-indexing Arrays

```php
public function removeTodo($index)
{
    unset($this->todos[$index]);
    $this->todos = array_values($this->todos); // Re-index
}
```

After `unset()`, use `array_values()` to re-index the array. This prevents gaps in indices.

### Array Filtering

```php
public function clearCompleted()
{
    $this->todos = array_values(
        array_filter($this->todos, fn($todo) => !$todo['completed'])
    );
}
```

Use `array_filter()` to remove items, then `array_values()` to re-index.

### Counting Items

```blade
{{ count(array_filter($todos, fn($t) => !$t['completed'])) }}
```

Count items matching a condition using `array_filter()` + `count()`.

## Enhancements

### Add Priorities

```php
use Diffyne\Attributes\Invokable;

public array $todos = [];

#[Invokable]
public function addTodo($priority = 'normal')
{
    $this->todos[] = [
        'text' => $this->newTodo,
        'completed' => false,
        'priority' => $priority,
    ];
    $this->newTodo = '';
}
```

```blade
<button diff:click="addTodo('high')" class="bg-red-500">High Priority</button>
<button diff:click="addTodo('normal')" class="bg-blue-500">Normal</button>
```

### Add Persistence

```php
use App\Models\Todo;
use Diffyne\Attributes\Invokable;

public function mount()
{
    $this->todos = Todo::where('user_id', auth()->id())
        ->orderBy('created_at', 'desc')
        ->get()
        ->toArray();
}

#[Invokable]
public function addTodo()
{
    $todo = Todo::create([
        'user_id' => auth()->id(),
        'text' => $this->newTodo,
        'completed' => false,
    ]);
    
    $this->todos[] = $todo->toArray();
    $this->newTodo = '';
}

#[Invokable]
public function removeTodo($index)
{
    Todo::find($this->todos[$index]['id'])->delete();
    unset($this->todos[$index]);
    $this->todos = array_values($this->todos);
}
```

### Add Editing

```php
use Diffyne\Attributes\Invokable;

public int $editingIndex = -1;
public string $editingText = '';

#[Invokable]
public function startEdit($index)
{
    $this->editingIndex = $index;
    $this->editingText = $this->todos[$index]['text'];
}

#[Invokable]
public function saveEdit()
{
    if ($this->editingIndex >= 0) {
        $this->todos[$this->editingIndex]['text'] = $this->editingText;
        $this->editingIndex = -1;
    }
}

#[Invokable]
public function cancelEdit()
{
    $this->editingIndex = -1;
}
```

```blade
@foreach($todos as $index => $todo)
    <li>
        @if($editingIndex === $index)
            <input 
                diff:model.defer="editingText"
                class="flex-1 px-2 py-1 border rounded">
            <button diff:click="saveEdit">Save</button>
            <button diff:click="cancelEdit">Cancel</button>
        @else
            <span>{{ $todo['text'] }}</span>
            <button diff:click="startEdit({{ $index }})">Edit</button>
        @endif
    </li>
@endforeach
```

### Add Categories

```php
use Diffyne\Attributes\Invokable;

public array $todos = [];
public string $filter = 'all'; // all, active, completed

public function mount()
{
    $this->loadTodos();
}

#[Invokable]
public function setFilter($filter)
{
    $this->filter = $filter;
}

public function getFilteredTodos()
{
    return match($this->filter) {
        'active' => array_filter($this->todos, fn($t) => !$t['completed']),
        'completed' => array_filter($this->todos, fn($t) => $t['completed']),
        default => $this->todos,
    };
}
```

```blade
<div class="flex gap-2 mb-4">
    <button 
        diff:click="setFilter('all')"
        class="{{ $filter === 'all' ? 'bg-blue-500 text-white' : 'bg-gray-200' }} px-3 py-1 rounded">
        All
    </button>
    <button 
        diff:click="setFilter('active')"
        class="{{ $filter === 'active' ? 'bg-blue-500 text-white' : 'bg-gray-200' }} px-3 py-1 rounded">
        Active
    </button>
    <button 
        diff:click="setFilter('completed')"
        class="{{ $filter === 'completed' ? 'bg-blue-500 text-white' : 'bg-gray-200' }} px-3 py-1 rounded">
        Completed
    </button>
</div>

@foreach($this->getFilteredTodos() as $index => $todo)
    {{-- Todo items --}}
@endforeach
```

## Next Steps

- [Contact Form Example](contact-form.md) - Forms with validation
- [Search Example](search.md) - Live search
- [Data Binding](../features/data-binding.md) - More about model binding
- [Forms](../features/forms.md) - Form handling details
