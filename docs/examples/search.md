# Live Search Example

A live search component with debouncing and real-time results.

## Component Code

### PHP Class

`app/Diffyne/UserSearch.php`:

```php
<?php

namespace App\Diffyne;

use App\Models\User;
use Diffyne\Component;

class UserSearch extends Component
{
    public string $search = '';
    public array $results = [];
    public bool $searching = false;
    
    public function mount()
    {
        $this->loadResults();
    }
    
    public function updated($field)
    {
        if ($field === 'search') {
            $this->loadResults();
        }
    }
    
    public function loadResults()
    {
        if (trim($this->search) === '') {
            $this->results = User::limit(10)->get()->toArray();
        } else {
            $this->results = User::where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->limit(10)
                ->get()
                ->toArray();
        }
    }
    
    public function clearSearch()
    {
        $this->search = '';
        $this->loadResults();
    }
}
```

### Blade View

`resources/views/diffyne/user-search.blade.php`:

```blade
<div class="max-w-2xl mx-auto p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold mb-4">User Search</h2>
        
        {{-- Search Input --}}
        <div class="relative">
            <input 
                type="text"
                diff:model.live.debounce.300="search"
                placeholder="Search users by name or email..."
                class="w-full px-4 py-3 pr-10 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            
            @if($search !== '')
                <button 
                    diff:click="clearSearch"
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    ✕
                </button>
            @endif
        </div>
        
        {{-- Loading indicator --}}
        <div diff:loading class="text-sm text-gray-500 mt-2">
            <span class="inline-block animate-pulse">Searching...</span>
        </div>
    </div>
    
    {{-- Results --}}
    <div class="space-y-2">
        @if(count($results) > 0)
            @foreach($results as $user)
                <div class="p-4 border rounded-lg hover:bg-gray-50 transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-lg">{{ $user['name'] }}</h3>
                            <p class="text-gray-600">{{ $user['email'] }}</p>
                        </div>
                        <div class="text-sm text-gray-500">
                            Joined {{ \Carbon\Carbon::parse($user['created_at'])->format('M Y') }}
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="text-center py-12 text-gray-500">
                @if($search !== '')
                    <p>No users found matching "{{ $search }}"</p>
                @else
                    <p>Enter a search term to find users</p>
                @endif
            </div>
        @endif
    </div>
    
    {{-- Result count --}}
    @if(count($results) > 0)
        <div class="mt-4 text-sm text-gray-600 text-center">
            Showing {{ count($results) }} result{{ count($results) !== 1 ? 's' : '' }}
        </div>
    @endif
</div>
```

### Usage

```blade
<diffyne:user-search />
```

## How It Works

### 1. Live Binding with Debounce

```blade
<input diff:model.live.debounce.300="search">
```

- `.live` - Syncs immediately as user types
- `.debounce.300` - Waits 300ms after user stops typing before syncing
- Prevents excessive server requests

### 2. Updated Hook

```php
public function updated($field)
{
    if ($field === 'search') {
        $this->loadResults();
    }
}
```

Automatically triggered when `$search` property changes. Reloads results with new search term.

### 3. Search Query

```php
public function loadResults()
{
    if (trim($this->search) === '') {
        $this->results = User::limit(10)->get()->toArray();
    } else {
        $this->results = User::where('name', 'like', "%{$this->search}%")
            ->orWhere('email', 'like', "%{$this->search}%")
            ->limit(10)
            ->get()
            ->toArray();
    }
}
```

Searches both `name` and `email` fields, limits to 10 results.

## Data Flow

```
User types "John" in search box
    ↓
After 300ms of inactivity...
    ↓
AJAX request: {method: 'update', property: 'search', value: 'John'}
    ↓
Server: $search property updated to 'John'
    ↓
Server: updated('search') hook triggered
    ↓
Server: loadResults() called
    ↓
Server: Database query executed
    ↓
Server: $results updated with matching users
    ↓
Server: View re-rendered
    ↓
Response: [patches with new result items]
    ↓
Browser: Results list updated
    ↓
UI: New results displayed
```

## Key Concepts

### Debouncing

Without debouncing, typing "John Smith" would send 10 requests (one per character).

With `.debounce.300`:
- Waits 300ms after last keystroke
- Only 1 request sent after user finishes typing
- Reduces server load by ~90%

### Loading State

```blade
<div diff:loading>Searching...</div>
```

Shows automatically while server is processing the request.

### Clearing Search

```php
public function clearSearch()
{
    $this->search = '';
    $this->loadResults();
}
```

Resets search and reloads initial results.

## Enhancements

### Add Filters

```php
public string $search = '';
public string $role = 'all';
public array $results = [];

public function updated($field)
{
    if (in_array($field, ['search', 'role'])) {
        $this->loadResults();
    }
}

public function loadResults()
{
    $query = User::query();
    
    if ($this->search !== '') {
        $query->where(function($q) {
            $q->where('name', 'like', "%{$this->search}%")
              ->orWhere('email', 'like', "%{$this->search}%");
        });
    }
    
    if ($this->role !== 'all') {
        $query->where('role', $this->role);
    }
    
    $this->results = $query->limit(10)->get()->toArray();
}
```

```blade
<select diff:model.live="role">
    <option value="all">All Roles</option>
    <option value="admin">Admin</option>
    <option value="user">User</option>
    <option value="moderator">Moderator</option>
</select>
```

### Add Pagination

```php
public int $page = 1;
public int $perPage = 10;

public function loadResults()
{
    $query = User::where('name', 'like', "%{$this->search}%");
    
    $this->results = $query
        ->skip(($this->page - 1) * $this->perPage)
        ->take($this->perPage)
        ->get()
        ->toArray();
}

public function nextPage()
{
    $this->page++;
    $this->loadResults();
}

public function previousPage()
{
    if ($this->page > 1) {
        $this->page--;
        $this->loadResults();
    }
}
```

```blade
<button diff:click="previousPage">Previous</button>
<span>Page {{ $page }}</span>
<button diff:click="nextPage">Next</button>
```

### Add Sort Options

```php
public string $sortBy = 'name';
public string $sortDir = 'asc';

public function sort($field)
{
    if ($this->sortBy === $field) {
        $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $field;
        $this->sortDir = 'asc';
    }
    
    $this->loadResults();
}

public function loadResults()
{
    $this->results = User::where('name', 'like', "%{$this->search}%")
        ->orderBy($this->sortBy, $this->sortDir)
        ->limit(10)
        ->get()
        ->toArray();
}
```

```blade
<button diff:click="sort('name')">
    Name {{ $sortBy === 'name' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
</button>
<button diff:click="sort('email')">
    Email {{ $sortBy === 'email' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
</button>
```

### Add Search Highlighting

```php
public function highlightSearch($text)
{
    if ($this->search === '') {
        return $text;
    }
    
    return preg_replace(
        '/(' . preg_quote($this->search, '/') . ')/i',
        '<mark class="bg-yellow-200">$1</mark>',
        $text
    );
}
```

```blade
<h3>{!! $this->highlightSearch($user['name']) !!}</h3>
<p>{!! $this->highlightSearch($user['email']) !!}</p>
```

### Add Search History

```php
public array $recentSearches = [];

public function updated($field)
{
    if ($field === 'search' && $this->search !== '') {
        $this->addToHistory($this->search);
        $this->loadResults();
    }
}

private function addToHistory($term)
{
    if (!in_array($term, $this->recentSearches)) {
        array_unshift($this->recentSearches, $term);
        $this->recentSearches = array_slice($this->recentSearches, 0, 5);
    }
}
```

```blade
@if(count($recentSearches) > 0)
    <div class="mb-4">
        <p class="text-sm text-gray-600 mb-2">Recent searches:</p>
        <div class="flex gap-2">
            @foreach($recentSearches as $term)
                <button 
                    diff:click="search = '{{ $term }}'"
                    class="px-3 py-1 bg-gray-100 rounded hover:bg-gray-200 text-sm">
                    {{ $term }}
                </button>
            @endforeach
        </div>
    </div>
@endif
```

## Performance Optimization

### Cache Results

```php
use Illuminate\Support\Facades\Cache;

public function loadResults()
{
    $cacheKey = 'search:' . md5($this->search);
    
    $this->results = Cache::remember($cacheKey, 60, function() {
        return User::where('name', 'like', "%{$this->search}%")
            ->limit(10)
            ->get()
            ->toArray();
    });
}
```

### Eager Load Relationships

```php
public function loadResults()
{
    $this->results = User::with(['posts', 'profile'])
        ->where('name', 'like', "%{$this->search}%")
        ->limit(10)
        ->get()
        ->toArray();
}
```

### Index Database Columns

```php
// In migration
Schema::table('users', function (Blueprint $table) {
    $table->index('name');
    $table->index('email');
});
```

## Best Practices

### 1. Use Appropriate Debounce Time

```blade
{{-- Fast search (instant feedback) --}}
<input diff:model.live.debounce.150="search">

{{-- Normal search (balanced) --}}
<input diff:model.live.debounce.300="search">

{{-- Slow search (reduce server load) --}}
<input diff:model.live.debounce.500="search">
```

### 2. Limit Results

```php
// Always limit query results
$query->limit(10)->get();
```

### 3. Show Loading State

```blade
<div diff:loading>Searching...</div>
```

### 4. Handle Empty States

```blade
@if(count($results) === 0)
    <p>No results found</p>
@endif
```

### 5. Optimize Queries

```php
// Select only needed columns
User::select('id', 'name', 'email')
    ->where('name', 'like', "%{$this->search}%")
    ->get();
```

## Next Steps

- [Data Binding](../features/data-binding.md) - More about model binding
- [Loading States](../features/loading-states.md) - Better UX
- [Performance](../advanced/performance.md) - Optimization tips
- [Todo List Example](todo-list.md) - Array manipulation
