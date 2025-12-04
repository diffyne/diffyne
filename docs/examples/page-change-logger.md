# Page Change Logger Example

A component that logs page changes and other events from other components, demonstrating cross-component event listening.

## Overview

This example demonstrates:
- Listening to events from other components
- Event-driven architecture
- Logging and tracking component interactions
- Multiple event listeners

## Component Structure

### PageChangeLogger Component

**`app/Diffyne/PageChangeLogger.php`**:

```php
<?php

namespace App\Diffyne;

use Diffyne\Attributes\Invokable;
use Diffyne\Attributes\On;
use Diffyne\Component;
use Illuminate\View\View;

class PageChangeLogger extends Component
{
    public array $logs = [];

    public int $totalChanges = 0;

    public function mount(): void
    {
        // Initialize with empty logs
        $this->logs = [];
        $this->totalChanges = 0;
    }

    /**
     * Listen to 'pageChanging' event from any component.
     */
    #[On('pageChanging')]
    public function handlePageChange(array $data): void
    {
        $this->totalChanges++;

        $this->logs[] = [
            'timestamp' => now()->format('H:i:s'),
            'page' => $data['newPage'] ?? 'unknown',
            'count' => $this->totalChanges,
        ];

        // Keep only last 5 logs
        if (count($this->logs) > 5) {
            array_shift($this->logs);
        }
    }

    /**
     * Listen to 'post-deleted' event.
     */
    #[On('post-deleted')]
    public function handleDataDeleted(array $data): void
    {
        $this->logs[] = [
            'timestamp' => now()->format('H:i:s'),
            'message' => 'Data was deleted',
            'data' => $data,
        ];

        // Keep only last 5 logs
        if (count($this->logs) > 5) {
            array_shift($this->logs);
        }
    }

    /**
     * Clear all logs.
     */
    #[Invokable]
    public function clearLogs(): void
    {
        $this->logs = [];
        $this->totalChanges = 0;
    }

    public function render(): View
    {
        return view('diffyne.page-change-logger');
    }
}
```

**`resources/views/diffyne/page-change-logger.blade.php`**:

```blade
<div class="bg-white shadow rounded-lg p-4">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">Event Logger</h3>
        <button
            diff:click="clearLogs"
            class="text-sm text-gray-600 hover:text-gray-800">
            Clear
        </button>
    </div>

    <div class="mb-2">
        <span class="text-sm text-gray-600">Total Changes: </span>
        <span class="font-semibold">{{ $totalChanges }}</span>
    </div>

    <div class="space-y-2">
        @if(empty($logs))
            <p class="text-sm text-gray-500">No events logged yet.</p>
        @else
            @foreach(array_reverse($logs) as $log)
                <div class="text-sm border-l-2 border-blue-500 pl-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ $log['timestamp'] }}</span>
                        @if(isset($log['page']))
                            <span class="font-semibold">Page {{ $log['page'] }}</span>
                        @endif
                    </div>
                    @if(isset($log['message']))
                        <p class="text-gray-700">{{ $log['message'] }}</p>
                    @endif
                    @if(isset($log['count']))
                        <p class="text-xs text-gray-500">Change #{{ $log['count'] }}</p>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</div>
```

## Usage Example

### PostList Component (Event Source)

**`app/Diffyne/PostList.php`**:

```php
<?php

namespace App\Diffyne;

use App\Models\Post;
use Diffyne\Attributes\Invokable;
use Diffyne\Attributes\Locked;
use Diffyne\Attributes\On;
use Diffyne\Component;
use Illuminate\View\View;

class PostList extends Component
{
    #[Locked]
    public array $posts = [];

    public int $page = 1;

    #[Locked]
    public int $totalPages = 1;

    public function mount(): void
    {
        $this->loadPosts();
    }

    private function loadPosts(): void
    {
        $this->posts = Post::paginate(10)->toArray();
        $this->totalPages = ceil(Post::count() / 10);
    }

    /**
     * Navigate to next page.
     */
    #[Invokable]
    public function nextPage(): void
    {
        if ($this->page < $this->totalPages) {
            $this->page++;
            $this->loadPosts();

            // Dispatch component event
            $this->dispatch('pageChanging', [
                'newPage' => $this->page,
                'totalPages' => $this->totalPages,
            ]);

            // Dispatch browser event
            $this->dispatchBrowserEvent('page-navigated', [
                'page' => $this->page,
                'total' => $this->totalPages,
            ]);
        }
    }

    /**
     * Navigate to previous page.
     */
    #[Invokable]
    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadPosts();

            // Dispatch component event
            $this->dispatch('pageChanging', [
                'newPage' => $this->page,
                'totalPages' => $this->totalPages,
            ]);

            // Dispatch browser event
            $this->dispatchBrowserEvent('page-navigated', [
                'page' => $this->page,
                'total' => $this->totalPages,
            ]);
        }
    }

    /**
     * Delete a post.
     */
    #[Invokable]
    public function deletePost(int $id): void
    {
        $post = Post::find($id);
        
        if ($post) {
            $post->delete();
            $this->loadPosts();

            // Dispatch event for logger
            $this->dispatch('post-deleted', [
                'id' => $id,
                'title' => $post->title,
            ]);
        }
    }

    public function render(): View
    {
        return view('diffyne.post-list');
    }
}
```

**`resources/views/diffyne/post-list.blade.php`**:

```blade
<div>
    <h1>Posts</h1>
    
    @foreach($posts as $post)
        <div class="post-item">
            <h2>{{ $post['title'] }}</h2>
            <p>{{ $post['content'] }}</p>
            <button diff:click="deletePost({{ $post['id'] }})">
                Delete
            </button>
        </div>
    @endforeach

    <div class="pagination">
        <button 
            diff:click="previousPage"
            diff:loading.attr="disabled"
            {{ $page <= 1 ? 'disabled' : '' }}>
            Previous
        </button>
        
        <span>Page {{ $page }} of {{ $totalPages }}</span>
        
        <button 
            diff:click="nextPage"
            diff:loading.attr="disabled"
            {{ $page >= $totalPages ? 'disabled' : '' }}>
            Next
        </button>
    </div>
</div>
```

### Layout

**`resources/views/layouts/app.blade.php`**:

```blade
<!DOCTYPE html>
<html>
<head>
    <title>My App</title>
</head>
<body>
    <main>
        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                @diffyne($component, $params ?? [])
            </div>
            
            <div class="col-span-1">
                {{-- Event logger sidebar --}}
                @diffyne('PageChangeLogger')
            </div>
        </div>
    </main>

    @diffyneScripts
</body>
</html>
```

## How It Works

1. **PostList dispatches events** when pages change or posts are deleted
2. **PageChangeLogger listens** to these events via `#[On]` attributes
3. **Logger updates its state** with event information
4. **View displays logs** in real-time as events occur

## Event Flow

```
PostList::nextPage()
    ↓
dispatch('pageChanging', [...])
    ↓
PageChangeLogger::handlePageChange()
    ↓
Update logs array
    ↓
View re-renders with new log entry
```

## Advanced Usage

### Listening to Multiple Events

```php
#[On('pageChanging')]
#[On('filterChanged')]
#[On('sortChanged')]
public function handleAnyChange(array $data): void
{
    $this->logs[] = [
        'timestamp' => now()->format('H:i:s'),
        'event' => 'change',
        'data' => $data,
    ];
}
```

### Filtering Events

```php
#[On('post-deleted')]
public function handlePostDeleted(array $data): void
{
    // Only log if post was important
    if (isset($data['important']) && $data['important']) {
        $this->logs[] = [
            'timestamp' => now()->format('H:i:s'),
            'message' => 'Important post deleted',
            'data' => $data,
        ];
    }
}
```

### Persisting Logs

```php
public function dehydrate(): void
{
    // Save logs to session
    session()->put('page-change-logs', $this->logs);
}

public function hydrate(): void
{
    // Restore logs from session
    $this->logs = session()->get('page-change-logs', []);
}
```

## Benefits

- **Decoupled**: Logger doesn't need to know about PostList
- **Reusable**: Can listen to events from any component
- **Flexible**: Easy to add new event listeners
- **Observable**: Track component interactions for debugging

## Use Cases

- **Debugging**: Track component interactions during development
- **Analytics**: Log user actions for analysis
- **Audit Trail**: Record important events
- **Development Tools**: Build developer tools that observe component behavior

## Next Steps

- [Component Events](features/component-events.md) - Learn about event system
- [Attributes](features/attributes.md) - Learn about #[On] attribute
- [Lifecycle Hooks](advanced/lifecycle-hooks.md) - Component lifecycle

