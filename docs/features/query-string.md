# Query String Binding

Sync component properties with URL query parameters for shareable, bookmarkable state.

## Overview

Query string binding automatically syncs component properties with URL query parameters. This enables:
- **Shareable URLs**: Users can share links with specific filters/search terms
- **Bookmarkable state**: Browser back/forward buttons work correctly
- **Deep linking**: Direct links to specific views/filters
- **State persistence**: Page refresh preserves state from URL

## Basic Usage

### Simple Binding

```php
use Diffyne\Attributes\QueryString;

class PostList extends Component
{
    #[QueryString]
    public string $search = '';
    
    #[QueryString]
    public int $page = 1;
    
    #[QueryString]
    public string $status = 'all';
}
```

When `$search` changes to `"laptop"`, the URL becomes:
```
/posts?search=laptop&page=1&status=all
```

### URL Behavior

- **Property changes** → URL updates automatically
- **URL changes** → Property updates automatically
- **Page refresh** → State restored from URL
- **Browser back/forward** → State synced with URL

## Parameters

### `as` - Custom Parameter Name

Use a different query parameter name:

```php
#[QueryString(as: 'q')]
public string $search = '';

// URL: /posts?q=laptop (instead of ?search=laptop)
```

### `keep` - Keep Empty Values

By default, empty values are removed from the URL. Use `keep: true` to preserve them:

```php
#[QueryString(keep: true)]
public ?string $filter = null;

// URL: /posts?filter= (even when empty)
```

### `history` - Browser History

Control whether URL changes push to browser history:

```php
#[QueryString(history: false)]
public string $view = 'grid';

// URL updates but doesn't add to history (replaces current entry)
```

## Examples

### Search with Pagination

```php
class ProductSearch extends Component
{
    #[QueryString]
    public string $q = '';
    
    #[QueryString]
    public int $page = 1;
    
    #[QueryString]
    public ?int $categoryId = null;
    
    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'categoryId'])) {
            $this->page = 1; // Reset to first page
        }
        $this->loadProducts();
    }
}
```

**URL Examples:**
- `/products?q=laptop&page=1`
- `/products?q=laptop&page=2&categoryId=5`
- `/products?categoryId=5&page=1`

### Filters with Custom Names

```php
class PostList extends Component
{
    #[QueryString(as: 'q')]
    public string $search = '';
    
    #[QueryString(as: 'cat')]
    public ?int $categoryId = null;
    
    #[QueryString(as: 'sort')]
    public string $sortBy = 'created_at';
    
    #[QueryString]
    public int $page = 1;
}
```

**URL:** `/posts?q=laptop&cat=5&sort=title&page=2`

### Keep Important State

```php
class Dashboard extends Component
{
    #[QueryString(keep: true)]
    public string $viewMode = 'grid'; // Always in URL
    
    #[QueryString]
    public ?string $filter = null; // Removed when empty
}
```

## Best Practices

### 1. Use for Shareable State

```php
// ✅ Good - users can share filtered views
#[QueryString]
public string $search = '';

#[QueryString]
public string $status = 'all';

// ❌ Avoid - internal state doesn't need URL
public bool $isExpanded = false;
```

### 2. Reset Related State

```php
#[QueryString]
public string $search = '';

#[QueryString]
public int $page = 1;

public function updatedSearch(): void
{
    // Reset page when search changes
    $this->page = 1;
    $this->loadResults();
}
```

### 3. Use Custom Names for Clean URLs

```php
// ✅ Good - shorter, cleaner URL
#[QueryString(as: 'q')]
public string $search = '';

// ⚠️ OK - but longer URL
#[QueryString]
public string $search = '';
```

### 4. Combine with Locked Properties

```php
#[QueryString]
public string $search = ''; // User can change

#[Locked]
public array $results = []; // Server-controlled

public function updatedSearch(): void
{
    // Server loads results based on search
    $this->loadResults();
}
```

## Advanced Patterns

### Conditional Query Parameters

```php
class ProductList extends Component
{
    #[QueryString]
    public string $search = '';
    
    #[QueryString]
    public ?int $categoryId = null;
    
    #[QueryString(keep: true)]
    public string $sort = 'name';
    
    public function getQueryString(): array
    {
        $query = [];
        
        if ($this->search) {
            $query['q'] = $this->search;
        }
        
        if ($this->categoryId) {
            $query['cat'] = $this->categoryId;
        }
        
        $query['sort'] = $this->sort;
        
        return $query;
    }
}
```

### Multiple Query String Properties

```php
class AdvancedSearch extends Component
{
    #[QueryString]
    public string $q = '';
    
    #[QueryString]
    public ?string $minPrice = null;
    
    #[QueryString]
    public ?string $maxPrice = null;
    
    #[QueryString]
    public array $tags = [];
    
    #[QueryString]
    public int $page = 1;
    
    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->page = 1; // Reset page on filter change
        }
        $this->search();
    }
}
```

## URL Structure

### Default Behavior

```php
#[QueryString]
public string $search = 'laptop';
public int $page = 2;

// URL: ?search=laptop&page=2
```

### Custom Names

```php
#[QueryString(as: 'q')]
public string $search = 'laptop';

// URL: ?q=laptop
```

### Empty Values

```php
#[QueryString]
public string $search = ''; // Empty

// URL: /posts (search removed)

#[QueryString(keep: true)]
public string $view = ''; // Empty but kept

// URL: /posts?view=
```

## Integration with Forms

Query string properties work seamlessly with forms:

```blade
<form>
    <input diff:model="search" placeholder="Search...">
    <select diff:model="status">
        <option value="all">All</option>
        <option value="active">Active</option>
    </select>
</form>
```

When user types or selects, URL updates automatically.

## Browser Navigation

### Back/Forward Buttons

Query string binding works with browser navigation:

1. User filters: `/posts?search=laptop&status=active`
2. User navigates away
3. User clicks back button
4. Component state restored from URL: `$search = 'laptop'`, `$status = 'active'`

### Direct Links

Users can bookmark or share URLs:

```
https://example.com/posts?search=laptop&status=active&page=2
```

When opened, component initializes with:
- `$search = 'laptop'`
- `$status = 'active'`
- `$page = 2`

## Common Use Cases

### 1. Search with Filters

```php
class ProductSearch extends Component
{
    #[QueryString]
    public string $q = '';
    
    #[QueryString]
    public ?int $categoryId = null;
    
    #[QueryString]
    public ?float $minPrice = null;
    
    #[QueryString]
    public ?float $maxPrice = null;
    
    #[QueryString]
    public int $page = 1;
}
```

### 2. Data Tables

```php
class UserTable extends Component
{
    #[QueryString]
    public string $search = '';
    
    #[QueryString]
    public string $sortBy = 'name';
    
    #[QueryString]
    public string $sortDir = 'asc';
    
    #[QueryString]
    public int $page = 1;
    
    #[QueryString]
    public int $perPage = 10;
}
```

### 3. Dashboard Views

```php
class Dashboard extends Component
{
    #[QueryString(keep: true)]
    public string $view = 'overview';
    
    #[QueryString]
    public ?string $dateRange = null;
    
    #[QueryString]
    public ?int $userId = null;
}
```

## Troubleshooting

### URL Not Updating

**Problem**: Property changes but URL doesn't update.

**Solution**: Ensure property is marked with `#[QueryString]`:

```php
// ✅ Correct
#[QueryString]
public string $search = '';

// ❌ Wrong - missing attribute
public string $search = '';
```

### State Not Restoring from URL

**Problem**: Page refresh doesn't restore state.

**Solution**: Check that `mount()` doesn't override URL values:

```php
public function mount(): void
{
    // ❌ Wrong - overrides URL value
    $this->search = '';
    
    // ✅ Correct - only set default if not in URL
    if (!$this->search) {
        $this->search = '';
    }
}
```

### Empty Values in URL

**Problem**: Empty values cluttering URL.

**Solution**: Remove `keep: true` or use conditional logic:

```php
// Remove empty values
#[QueryString] // keep defaults to false
public ?string $filter = null;
```

## Next Steps

- [Attributes](attributes.md) - Learn about QueryString attribute
- [Component State](advanced/component-state.md) - State management
- [Security](advanced/security.md) - Security considerations
- [Examples](../examples/) - More examples

