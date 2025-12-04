# Redirects

Diffyne provides methods for redirecting users to different pages using SPA (Single Page Application) navigation, which provides a smoother user experience than full page reloads.

## Overview

Diffyne's redirect methods:
- Use SPA navigation by default (client-side routing)
- Preserve component state during navigation
- Provide faster transitions than full page reloads
- Support both URL and route-based redirects

## Available Methods

### redirect()

Redirect to a URL:

```php
public function save(): void
{
    // Save data...
    
    $this->redirect('/posts');
}
```

### redirectRoute()

Redirect to a named route:

```php
public function save(): void
{
    // Save data...
    
    $this->redirectRoute('posts.index');
}

// With parameters
public function viewPost(int $id): void
{
    $this->redirectRoute('posts.show', ['id' => $id]);
}
```

### redirectBack()

Redirect to the previous page:

```php
public function cancel(): void
{
    $this->redirectBack();
}
```

## SPA vs Full Page Reload

### SPA Navigation (Default)

```php
// Uses client-side navigation (faster, smoother)
$this->redirect('/posts');
```

**Benefits:**
- Faster transitions
- Preserves JavaScript state
- Smoother user experience
- No full page reload

### Full Page Reload

```php
// Force full page reload
$this->redirect('/posts', spa: false);
```

**Use when:**
- You need to clear all state
- Redirecting after logout
- Security-sensitive operations

## Examples

### Form Submission with Redirect

```php
class PostForm extends Component
{
    public string $title = '';
    public string $content = '';
    
    #[Invokable]
    public function save(): void
    {
        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);
        
        Post::create($validated);
        
        // Redirect to posts list
        $this->redirect('/posts');
    }
    
    #[Invokable]
    public function cancel(): void
    {
        // Go back to previous page
        $this->redirectBack();
    }
}
```

### Conditional Redirects

```php
#[Invokable]
public function login(): void
{
    $validated = $this->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);
    
    if (auth()->attempt($validated)) {
        // Redirect based on user role
        if (auth()->user()->isAdmin()) {
            $this->redirectRoute('admin.dashboard');
        } else {
            $this->redirectRoute('dashboard');
        }
    } else {
        $this->addError('email', 'Invalid credentials');
    }
}
```

### Redirect After Delete

```php
#[Invokable]
public function delete(int $id): void
{
    $post = Post::find($id);
    
    if ($post) {
        $post->delete();
        
        // Redirect to list
        $this->redirect('/posts');
    }
}
```

### Redirect with Query Parameters

```php
#[Invokable]
public function search(): void
{
    // Redirect to search results
    $this->redirect('/posts?search=' . urlencode($this->query));
}
```

## Best Practices

### 1. Use SPA Navigation for Better UX

```php
// ✅ Good - SPA navigation (default)
$this->redirect('/posts');

// ⚠️ Use full reload only when necessary
$this->redirect('/posts', spa: false);
```

### 2. Redirect After Successful Operations

```php
#[Invokable]
public function save(): void
{
    $this->validate();
    
    // Save data...
    
    // Always redirect after success
    $this->redirect('/posts');
}
```

### 3. Use Named Routes When Possible

```php
// ✅ Good - More maintainable
$this->redirectRoute('posts.show', ['id' => $id]);

// ⚠️ OK - But less maintainable
$this->redirect("/posts/{$id}");
```

### 4. Handle Errors Before Redirecting

```php
#[Invokable]
public function save(): void
{
    try {
        $this->validate();
        // Save data...
        $this->redirect('/posts');
    } catch (ValidationException $e) {
        // Don't redirect on validation errors
        // Errors are automatically displayed
    }
}
```

## Integration with Forms

Redirects work seamlessly with form submissions:

```blade
<form diff:submit="save">
    <input diff:model="title">
    <button type="submit">Save</button>
</form>
```

```php
#[Invokable]
public function save(): void
{
    $this->validate();
    // Save...
    $this->redirect('/posts');
}
```

## Common Patterns

### Create → List

```php
#[Invokable]
public function create(): void
{
    $this->validate();
    Post::create($this->validated);
    $this->redirect('/posts');
}
```

### Update → Show

```php
#[Invokable]
public function update(int $id): void
{
    $this->validate();
    Post::find($id)->update($this->validated);
    $this->redirectRoute('posts.show', ['id' => $id]);
}
```

### Delete → List

```php
#[Invokable]
public function delete(int $id): void
{
    Post::find($id)->delete();
    $this->redirect('/posts');
}
```

### Cancel → Back

```php
#[Invokable]
public function cancel(): void
{
    $this->redirectBack();
}
```

## Next Steps

- [Forms](forms.md) - Form handling
- [Validation](validation.md) - Form validation
- [Component Events](component-events.md) - Event system

