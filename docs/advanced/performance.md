# Performance Optimization

Tips and techniques to maximize Diffyne performance.

## Database Optimization

### 1. Limit Query Results

```php
// Good
$this->items = Item::limit(50)->get();

// Avoid
$this->items = Item::all(); // Could be thousands
```

### 2. Eager Load Relationships

```php
// Good
$this->posts = Post::with(['user', 'comments'])->get();

// Avoid - N+1 problem
$this->posts = Post::all();
// Then accessing $post->user in loop
```

### 3. Select Only Needed Columns

```php
// Good
$this->users = User::select('id', 'name', 'email')->get();

// Avoid
$this->users = User::all(); // Loads all columns
```

### 4. Use Caching

```php
use Illuminate\Support\Facades\Cache;

public function loadStats()
{
    $this->stats = Cache::remember('dashboard-stats', 60, function() {
        return [
            'users' => User::count(),
            'posts' => Post::count(),
            'revenue' => Order::sum('total'),
        ];
    });
}
```

## Component Optimization

### 1. Use Deferred Binding

```blade
{{-- Good - syncs once on submit --}}
<input diff:model.defer="name">

{{-- Avoid - syncs on every keystroke --}}
<input diff:model.live="name">
```

### 2. Add Debouncing

```blade
{{-- Good - waits 300ms after typing stops --}}
<input diff:model.live.debounce.300="search">

{{-- Avoid - sends request on every keystroke --}}
<input diff:model.live="search">
```

### 3. Optimize Polling Intervals

```blade
{{-- Good - reasonable interval --}}
<div diff:poll.5s="refresh">

{{-- Avoid - too frequent --}}
<div diff:poll.100ms="refresh">
```

### 4. Limit Component Size

```php
// Good - focused component
class TodoList extends Component
{
    public array $todos = [];
    public function addTodo() { /* ... */ }
}

// Avoid - too many responsibilities
class Dashboard extends Component
{
    public array $todos, $users, $posts, $stats, $notifications;
    // 20+ methods...
}
```

## State Optimization

### 1. Keep State Minimal

```php
// Good
public int $userId;
public function hydrate()
{
    $this->user = User::find($this->userId);
}

// Avoid - serializing entire model
public User $user;
```

### 2. Use Computed Properties

```php
// Good
public function getTotalPrice()
{
    return array_sum(array_column($this->items, 'price'));
}

// Avoid - storing computed value
public float $totalPrice;
public function updated()
{
    $this->totalPrice = array_sum(...);
}
```

### 3. Clean Up Temporary Data

```php
public function dehydrate()
{
    unset($this->temporaryData);
    unset($this->largeObject);
}
```

## Frontend Optimization

### 1. Minimize DOM Updates

The Virtual DOM handles this automatically, but you can help:

```blade
{{-- Good - conditional rendering --}}
@if($showDetails)
    <div>...</div>
@endif

{{-- Avoid - always rendering hidden element --}}
<div class="{{ $showDetails ? '' : 'hidden' }}">...</div>
```

### 2. Use Keys for Lists

```blade
@foreach($items as $item)
    <li key="{{ $item['id'] }}">{{ $item['name'] }}</li>
@endforeach
```

### 3. Lazy Load Images

```blade
<img src="placeholder.jpg" data-src="{{ $image }}" loading="lazy">
```

## Network Optimization

### 1. Batch Operations

```php
// Good - single request
public function deleteSelected()
{
    Item::whereIn('id', $this->selectedIds)->delete();
}

// Avoid - multiple requests
foreach ($selectedIds as $id) {
    // Triggers separate request per item
}
```

### 2. Use Compression

Diffyne automatically minifies responses. Ensure gzip is enabled on your server.

### 3. CDN for Assets

Serve `diffyne.js` from CDN for better caching.

## Benchmarking

### Measure Component Performance

```php
public function expensiveOperation()
{
    $start = microtime(true);
    
    // Your code here
    
    $time = microtime(true) - $start;
    logger("Operation took: " . $time . "s");
}
```

### Monitor Database Queries

```php
DB::enableQueryLog();

$this->loadData();

$queries = DB::getQueryLog();
logger("Queries executed: " . count($queries));
```

## Production Checklist

- [ ] Enable caching (`DIFFYNE_CACHE=true`)
- [ ] Disable debug mode (`DIFFYNE_DEBUG=false`)
- [ ] Optimize database queries
- [ ] Add database indexes
- [ ] Use deferred/debounced model binding
- [ ] Limit query results
- [ ] Eager load relationships
- [ ] Enable gzip compression
- [ ] Use CDN for static assets
- [ ] Monitor with Laravel Telescope/Debugbar

## Performance Comparison

Typical operation times:

| Operation | Time |
|-----------|------|
| Counter increment | 50-80ms |
| Form submission | 100-200ms |
| Search query | 150-300ms |
| List update | 80-150ms |

Payload sizes:

| Operation | Payload |
|-----------|---------|
| Counter increment | ~50 bytes |
| Todo add | ~150 bytes |
| Search results | ~500-2000 bytes |

## Next Steps

- [Virtual DOM](virtual-dom.md) - How Diffyne achieves small payloads
- [Component State](component-state.md) - State management
- [Testing](testing.md) - Test your components
