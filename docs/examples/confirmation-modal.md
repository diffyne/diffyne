# Confirmation Modal Example

A reusable confirmation modal component that can be triggered from any component via events.

## Overview

This example demonstrates:
- Cross-component communication via events
- Reusable utility components
- Locked properties for security
- Event dispatching and listening

## Component Structure

### ConfirmationModal Component

**`app/Diffyne/Utils/ConfirmationModal.php`**:

```php
<?php

namespace App\Diffyne\Utils;

use Diffyne\Attributes\Invokable;
use Diffyne\Attributes\Locked;
use Diffyne\Attributes\On;
use Diffyne\Component;
use Illuminate\View\View;

class ConfirmationModal extends Component
{
    public bool $isOpen = false;
    
    #[Locked]
    public string $title = 'Confirm Action';
    
    #[Locked]
    public string $message = 'Are you sure you want to proceed?';
    
    #[Locked]
    public string $confirmText = 'Confirm';
    
    #[Locked]
    public string $cancelText = 'Cancel';
    
    #[Locked]
    public string $eventName = '';
    
    #[Locked]
    public array $eventData = [];

    /**
     * Open the modal with custom content.
     */
    #[On('open-confirmation-modal')]
    #[Invokable]
    public function open(array $data): void
    {
        $this->isOpen = true;
        $this->title = $data['title'] ?? 'Confirm Action';
        $this->message = $data['message'] ?? 'Are you sure you want to proceed?';
        $this->confirmText = $data['confirmText'] ?? 'Confirm';
        $this->cancelText = $data['cancelText'] ?? 'Cancel';
        $this->eventName = $data['eventName'] ?? '';
        $this->eventData = $data['eventData'] ?? [];
    }

    /**
     * Close the modal.
     */
    #[Invokable]
    public function close(): void
    {
        $this->isOpen = false;
        $this->eventName = '';
        $this->title = 'Confirm Action';
        $this->message = 'Are you sure you want to proceed?';
        $this->confirmText = 'Confirm';
        $this->cancelText = 'Cancel';
        $this->eventData = [];
    }

    /**
     * Confirm action and dispatch the event.
     */
    #[Invokable]
    public function confirm(): void
    {
        if ($this->eventName) {
            $this->dispatch($this->eventName, $this->eventData);
        }
        $this->close();
    }

    public function render(): View
    {
        return view('diffyne.utils.confirmation-modal');
    }
}
```

**`resources/views/diffyne/utils/confirmation-modal.blade.php`**:

```blade
@if($isOpen)
    <div 
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true">
        <!-- Background overlay -->
        <div 
            class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
            diff:click="close">
        </div>

        <!-- Modal panel -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">
                                {{ $title }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    {{ $message }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button
                        type="button"
                        diff:click="confirm"
                        class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">
                        {{ $confirmText }}
                    </button>
                    <button
                        type="button"
                        diff:click="close"
                        class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                        {{ $cancelText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
```

## Usage Example

### PostList Component

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

    public function mount(): void
    {
        $this->loadPosts();
    }

    private function loadPosts(): void
    {
        $this->posts = Post::all()->toArray();
    }

    /**
     * Open confirmation modal before deleting.
     */
    #[Invokable]
    public function confirmDelete(int $id): void
    {
        $post = Post::find($id);
        
        if ($post) {
            $this->dispatch('open-confirmation-modal', [
                'title' => 'Delete Post',
                'message' => "Are you sure you want to delete \"{$post->title}\"? This action cannot be undone.",
                'confirmText' => 'Delete',
                'cancelText' => 'Cancel',
                'eventName' => 'delete-post-confirmed',
                'eventData' => ['id' => $id],
            ]);
        }
    }

    /**
     * Handle confirmed deletion.
     */
    #[On('delete-post-confirmed')]
    public function deletePost(array $data): void
    {
        $id = $data['id'] ?? null;
        
        if (!$id) {
            return;
        }

        $post = Post::find($id);

        if ($post) {
            $postTitle = $post->title;
            $post->delete();
            $this->loadPosts();

            // Dispatch browser event for notifications
            $this->dispatchBrowserEvent('post-deleted', [
                'title' => $postTitle,
                'message' => 'Post deleted successfully!',
                'type' => 'success',
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
            <button 
                diff:click="confirmDelete({{ $post['id'] }})"
                class="text-red-600 hover:text-red-800">
                Delete
            </button>
        </div>
    @endforeach
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
        @diffyne($component, $params ?? [])
    </main>

    {{-- Include confirmation modal globally --}}
    @diffyne('Utils/ConfirmationModal')

    @diffyneScripts
</body>
</html>
```

## How It Works

1. **User clicks delete button** → `confirmDelete()` is called
2. **Component dispatches event** → `open-confirmation-modal` with modal configuration
3. **ConfirmationModal listens** → Opens with custom title, message, etc.
4. **User confirms** → `confirm()` dispatches the configured event (`delete-post-confirmed`)
5. **PostList listens** → `deletePost()` handles the actual deletion
6. **Modal closes** → User sees updated list

## Benefits

- **Reusable**: One modal component for all confirmations
- **Decoupled**: Components don't need to know about modal implementation
- **Flexible**: Customize title, message, buttons per use case
- **Secure**: Locked properties prevent tampering

## Advanced Usage

### Custom Styling

```php
$this->dispatch('open-confirmation-modal', [
    'title' => 'Delete Post',
    'message' => 'Are you sure?',
    'confirmText' => 'Yes, Delete',
    'cancelText' => 'Cancel',
    'eventName' => 'delete-post-confirmed',
    'eventData' => ['id' => $id],
    'confirmClass' => 'bg-red-600', // Custom styling
    'cancelClass' => 'bg-gray-200',
]);
```

### Multiple Confirmation Types

```php
// Delete confirmation
$this->dispatch('open-confirmation-modal', [
    'title' => 'Delete',
    'message' => 'This cannot be undone.',
    'confirmText' => 'Delete',
    'eventName' => 'delete-confirmed',
]);

// Publish confirmation
$this->dispatch('open-confirmation-modal', [
    'title' => 'Publish Post',
    'message' => 'This will make the post visible to everyone.',
    'confirmText' => 'Publish',
    'eventName' => 'publish-confirmed',
]);
```

## Next Steps

- [Component Events](features/component-events.md) - Learn about event system
- [Attributes](features/attributes.md) - Learn about #[On] and #[Locked]
- [Security](advanced/security.md) - Security best practices

