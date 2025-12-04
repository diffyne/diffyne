# Directives Overview

Diffyne provides a set of directives (special attributes) that enable reactive behavior in your components. All directives are prefixed with `diffyne:`.

## Quick Reference

| Directive | Purpose | Example |
|-----------|---------|---------||
| `diffyne:click` | Call method on click | `<button diff:click="save">` |
| `diffyne:change` | Call method on change | `<select diff:change="updateFilter">` |
| `diffyne:model` | Two-way data binding | `<input diff:model="name">` |
| `diffyne:submit` | Handle form submission | `<form diff:submit="submit">` |
| `diffyne:poll` | Poll server periodically | `<div diff:poll="5000">` |
| `diffyne:loading` | Show loading state | `<button diffyne:loading.class.opacity-50>` |
| `diffyne:error` | Display validation errors | `<span diff:error="email">` |

## Event Directives

### diffyne:click

Triggers when element is clicked.

```blade
<button diff:click="save">Save</button>
<button diff:click="delete({{ $id }})">Delete</button>
```

**Note:** Event modifiers like `.prevent` or `.stop` are not supported. Handle event behavior in your component methods using the event parameter if needed.

[Learn more about click events →](click-events.md)

### diffyne:change

Triggers when form element value changes.

```blade
<select diff:change="updateCategory">
    <option value="all">All</option>
    <option value="active">Active</option>
</select>

<input type="checkbox" diff:change="toggleStatus">
```

### diffyne:submit

Handles form submission.

```blade
<form diff:submit="submit">
    <input type="text" diff:model="name">
    <button type="submit">Submit</button>
</form>
```

**Note:** The form's default submission is automatically prevented by Diffyne when using `diffyne:submit`.

[Learn more about forms →](forms.md)

## Data Binding

### diffyne:model

Creates two-way data binding between input and component property.

```blade
{{-- Text input --}}
<input type="text" diff:model="username">

{{-- Checkbox --}}
<input type="checkbox" diff:model="active">

{{-- Select --}}
<select diff:model="category">
    <option>Option 1</option>
</select>

{{-- Textarea --}}
<textarea diff:model="description"></textarea>
```

**Modifiers:**

- `.lazy` - Sync on change event instead of input
- `.live` - Sync with server immediately on input
- `.debounce.{ms}` - Debounce updates (requires .live)

```blade
{{-- No modifiers: Updates local state only, syncs on change event --}}
<input diff:model="search">

{{-- Sync on blur/change only --}}
<input diff:model.lazy="email">

{{-- Sync immediately on every keystroke --}}
<input diff:model.live="search">

{{-- Sync after 300ms of inactivity --}}
<input diffyne:model.live.debounce.300="search">
```

[Learn more about data binding →](data-binding.md)

## Loading States

### diffyne:loading

Shows/hides elements or adds classes during server requests.

```blade
{{-- Add class while loading --}}
<button 
    diff:click="save"
    diffyne:loading.class.opacity-50>
    Save
</button>

{{-- Multiple classes --}}
<button 
    diff:click="save"
    diffyne:loading.class.opacity-50.cursor-not-allowed>
    Save
</button>

{{-- Show loading spinner (default opacity/pointer-events) --}}
<button diff:click="save">
    Save
    <span diffyne:loading>
        <svg class="spinner">...</svg>
    </span>
</button>
```

**Note:** Without `.class` modifier, elements get default loading styles (`opacity: 0.5` and `pointer-events: none`).

[Learn more about loading states →](loading-states.md)

## Polling

### diffyne:poll

Automatically call a method at regular intervals.

```blade
{{-- Poll every 5 seconds (5000ms) --}}
<div diff:poll="5000" diff:poll.action="refresh">
    Last updated: {{ $lastUpdate }}
</div>

{{-- Poll every 1 second with default action --}}
<div diff:poll="1000">
    Status: {{ $status }}
</div>

{{-- Poll every 2500 milliseconds --}}
<div diff:poll="2500" diff:poll.action="updateData">
    Data: {{ $data }}
</div>
```

**Attributes:**
- `diff:poll="{milliseconds}"` - The interval in milliseconds (default: 2000)
- `diff:poll.action="{method}"` - Method to call (default: 'refresh')

[Learn more about polling →](polling.md)

## Error Handling

### diffyne:error

Automatically displays validation errors for a field.

```blade
<input 
    type="email" 
    diff:model="email"
    class="border">

{{-- Error message appears here when validation fails --}}
<span diff:error="email" class="text-red-500"></span>
```

When validation fails, the error message is automatically inserted into the element.

[Learn more about validation →](validation.md)

## Modifier Chaining

Many directives support chaining modifiers:

```blade
{{-- Form with prevent default --}}
<form diff:submit.prevent="submit">

{{-- Model with live + debounce --}}
<input diffyne:model.live.debounce.300="search">

{{-- Click with stop propagation --}}
<div diff:click.stop="handleClick">
```

## Common Patterns

### Form with Validation

```blade
<form diff:submit="submit">
    <div>
        <input 
            type="email" 
            diff:model="email"
            class="border">
        <span diff:error="email"></span>
    </div>
    
    <button 
        type="submit"
        diffyne:loading.class.opacity-50>
        Submit
        <span diffyne:loading>...</span>
    </button>
</form>
```

### Live Search

```blade
<input 
    type="text" 
    diffyne:model.live.debounce.300="search"
    placeholder="Search...">

<div diffyne:loading.remove>
    Searching...
</div>

<div>
    @foreach($results as $result)
        <div>{{ $result }}</div>
    @endforeach
</div>
```

### Real-time Dashboard

```blade
<div diff:poll="5000" diff:poll.action="refreshStats">
    <div>Active Users: {{ $activeUsers }}</div>
    <div>Revenue: ${{ $revenue }}</div>
    
    <small diffyne:loading>Updating...</small>
</div>
```

## Next Steps

- [Click Events](click-events.md) - Handle user interactions
- [Data Binding](data-binding.md) - Two-way data sync
- [Forms](forms.md) - Form handling and submission
- [Validation](validation.md) - Form validation
- [Loading States](loading-states.md) - Better UX
- [Polling](polling.md) - Real-time updates
