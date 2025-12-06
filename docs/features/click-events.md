# Click Events

Handle user interactions with the `diff:click` directive.

## Basic Usage

Call a component method when an element is clicked:

```blade
<button diff:click="save">Save</button>
```

In your component:

```php
public function save()
{
    // Handle save logic
    $this->message = 'Saved successfully!';
}
```

## Passing Parameters

### Simple Parameters

```blade
<button diff:click="delete({{ $id }})">Delete</button>
<button diff:click="setStatus('active')">Activate</button>
<button diff:click="calculate(10, 20)">Calculate</button>
```

Component methods:

```php
public function delete($id)
{
    $this->items = array_filter($this->items, fn($item) => $item['id'] !== $id);
}

public function setStatus($status)
{
    $this->status = $status;
}

public function calculate($a, $b)
{
    $this->result = $a + $b;
}
```

### Passing Property Values

```blade
<button diff:click="setCategory('{{ $category }}')">
    {{ $category }}
</button>
```

### Multiple Parameters

```blade
@foreach($items as $index => $item)
    <button diff:click="updateItem({{ $index }}, '{{ $item['status'] }}')">
        Update
    </button>
@endforeach
```

## Event Handling

The `diff:click` directive automatically handles the click event and sends a request to the server. No event modifiers like `.prevent` or `.stop` are supported - handle event behavior in your component methods if needed.
    Click me
</a>
```

## Loading States

Show visual feedback during requests:

```blade
<button 
    diff:click="save"
    diff:loading.class="opacity-50 cursor-not-allowed">
    Save
</button>

<button diff:click="delete">
    <span diff:loading.remove>Delete</span>
    <span diff:loading>Deleting...</span>
</button>
```

## Conditional Clicks

Use Blade conditionals to control behavior:

```blade
@if($canEdit)
    <button diff:click="edit">Edit</button>
@endif

<button 
    diff:click="save"
    @if(!$isValid) disabled @endif>
    Save
</button>
```

## Examples

### Delete with Confirmation

```blade
<button 
    diff:click="delete"
    onclick="return confirm('Are you sure?')">
    Delete
</button>
```

Component:

```php
public function delete()
{
    // Will only run if user confirms
    DB::table('items')->where('id', $this->id)->delete();
    $this->deleted = true;
}
```

### Toggle State

```blade
<button diff:click="toggle">
    {{ $isActive ? 'Disable' : 'Enable' }}
</button>
```

Component:

```php
public bool $isActive = false;

public function toggle()
{
    $this->isActive = !$this->isActive;
}
```

### List Actions

```blade
<ul>
    @foreach($items as $index => $item)
        <li>
            {{ $item['name'] }}
            <button diff:click="edit({{ $index }})">Edit</button>
            <button diff:click="remove({{ $index }})">Remove</button>
        </li>
    @endforeach
</ul>
```

Component:

```php
public array $items = [];
public int $editingIndex = -1;

public function edit($index)
{
    $this->editingIndex = $index;
}

public function remove($index)
{
    unset($this->items[$index]);
    $this->items = array_values($this->items);
}
```

### Increment/Decrement

```blade
<div>
    <button diff:click="decrement">-</button>
    <span class="mx-4 text-2xl">{{ $count }}</span>
    <button diff:click="increment">+</button>
</div>
```

Component:

```php
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
```

### Pagination

```blade
<div>
    <button 
        diff:click="previousPage"
        @if($page === 1) disabled @endif>
        Previous
    </button>
    
    <span>Page {{ $page }}</span>
    
    <button 
        diff:click="nextPage"
        @if($page === $totalPages) disabled @endif>
        Next
    </button>
</div>
```

Component:

```php
public int $page = 1;
public int $totalPages = 10;

public function nextPage()
{
    if ($this->page < $this->totalPages) {
        $this->page++;
    }
}

public function previousPage()
{
    if ($this->page > 1) {
        $this->page--;
    }
}
```

## Best Practices

### 1. Use Descriptive Method Names

```blade
{{-- Good --}}
<button diff:click="saveUserProfile">Save</button>
<button diff:click="deletePost">Delete</button>

{{-- Avoid --}}
<button diff:click="action1">Save</button>
<button diff:click="doIt">Delete</button>
```

### 2. Validate on Server

Never trust client-side validation alone:

```php
public function delete($id)
{
    // Validate user has permission
    if (!auth()->user()->can('delete', $this->item)) {
        $this->addError('general', 'Unauthorized');
        return;
    }
    
    // Proceed with deletion
    $this->item->delete();
}
```

### 3. Provide Feedback

Always give user feedback:

```blade
<button diff:click="save">
    Save
    <span diff:loading>Saving...</span>
</button>

@if($saved)
    <div class="success-message">Saved successfully!</div>
@endif
```

### 4. Handle Errors Gracefully

```php
public function save()
{
    try {
        // Save logic
        $this->message = 'Saved successfully!';
    } catch (\Exception $e) {
        $this->addError('general', 'Failed to save. Please try again.');
    }
}
```

## Performance Tips

1. **Avoid unnecessary clicks**: Don't call methods that don't change state
2. **Use debouncing**: For rapid clicks, add delay on frontend
3. **Optimize methods**: Keep click handlers fast and efficient
4. **Loading states**: Always show loading feedback for slow operations

## Next Steps

- [Data Binding](data-binding.md) - Two-way data sync
- [Forms](forms.md) - Form handling
- [Loading States](loading-states.md) - Better UX during requests
- [Examples](../examples/) - More real-world examples
