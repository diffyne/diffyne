# Data Binding

Two-way data binding with `diffyne:model` keeps your component properties in sync with form inputs.

## Basic Usage

Bind an input to a component property:

```blade
<input type="text" diff:model="username">
```

Component:

```php
public string $username = '';
```

When the user types, `$username` updates automatically. When `$username` changes server-side, the input updates.

## Supported Elements

### Text Input

```blade
<input type="text" diff:model="name">
<input type="email" diff:model="email">
<input type="password" diff:model="password">
<input type="number" diff:model="age">
```

### Textarea

```blade
<textarea diff:model="description"></textarea>
```

### Checkbox

```blade
<input type="checkbox" diff:model="active">
```

Component:

```php
public bool $active = false;
```

### Radio Buttons

```blade
<input type="radio" diff:model="status" value="active"> Active
<input type="radio" diff:model="status" value="inactive"> Inactive
```

Component:

```php
public string $status = 'active';
```

### Select

```blade
<select diff:model="category">
    <option value="all">All</option>
    <option value="active">Active</option>
    <option value="archived">Archived</option>
</select>
```

### Multiple Select

```blade
<select multiple diff:model="tags">
    <option value="php">PHP</option>
    <option value="laravel">Laravel</option>
    <option value="javascript">JavaScript</option>
</select>
```

Component:

```php
public array $tags = [];
```

## Modifiers

### .lazy

Syncs on `change` event instead of `input`:

```blade
<input diff:model.lazy="search">
```

**Use case:** Sync when input loses focus, not on every keystroke.

### .live

Syncs with server immediately on every input:

```blade
<input diff:model.live="search">
```

**Use case:** Real-time search or filtering. Without `.live`, model only updates local state.

### .debounce.{ms}

Waits X milliseconds after user stops typing (requires `.live`):

```blade
<input diffyne:model.live.debounce.300="search">
```

**Use case:** Search inputs to avoid excessive server requests.

### Combining Modifiers

```blade
{{-- Live binding with 500ms debounce --}}
<input diffyne:model.live.debounce.500="search">
```

## Examples

### Live Search

```blade
<input 
    type="text" 
    diffyne:model.live.debounce.300="search"
    placeholder="Search users...">

<div diffyne:loading>Searching...</div>

<ul>
    @foreach($results as $user)
        <li>{{ $user->name }}</li>
    @endforeach
</ul>
```

Component:

```php
use App\Models\User;

class UserSearch extends Component
{
    public string $search = '';
    public $results = [];
    
    public function updated(string $property)
    {
        if ($property === 'search') {
            $this->results = User::where('name', 'like', "%{$this->search}%")
                ->limit(10)
                ->get();
        }
    }
}
```

### Contact Form

```blade
<form diff:submit="submit">
    <div>
        <label>Name</label>
        <input diff:model="name">
        <span diff:error="name"></span>
    </div>
    
    <div>
        <label>Email</label>
        <input type="email" diff:model="email">
        <span diff:error="email"></span>
    </div>
    
    <div>
        <label>Message</label>
        <textarea diff:model="message"></textarea>
        <span diff:error="message"></span>
    </div>
    
    <button type="submit" diffyne:loading.class.opacity-50>
        Submit
    </button>
</form>
```

Component:

```php
class ContactForm extends Component
{
    public string $name = '';
    public string $email = '';
    public string $message = '';
    
    protected function rules(): array
    {
        return [
            'name' => 'required|min:3',
            'email' => 'required|email',
            'message' => 'required|min:10',
        ];
    }
    
    public function submit()
    {
        $this->validate();
        
        // Send email...
        
        // Reset form
        $this->reset('name', 'email', 'message');
    }
}
```

### Filter Component

```blade
<div>
    <select diff:model.live="category">
        <option value="all">All Categories</option>
        <option value="electronics">Electronics</option>
        <option value="clothing">Clothing</option>
    </select>
    
    <select diff:model.live="sort">
        <option value="name">Name</option>
        <option value="price">Price</option>
    </select>
    
    <label>
        <input type="checkbox" diff:model.live="inStock">
        In Stock Only
    </label>
    
    <div>
        @foreach($products as $product)
            <div>{{ $product->name }} - ${{ $product->price }}</div>
        @endforeach
    </div>
</div>
```

Component:

```php
use App\Models\Product;

class ProductFilter extends Component
{
    public string $category = 'all';
    public string $sort = 'name';
    public bool $inStock = false;
    public $products = [];
    
    public function mount()
    {
        $this->loadProducts();
    }
    
    public function updated(string $property)
    {
        $this->loadProducts();
    }
    
    private function loadProducts()
    {
        $query = Product::query();
        
        if ($this->category !== 'all') {
            $query->where('category', $this->category);
        }
        
        if ($this->inStock) {
            $query->where('stock', '>', 0);
        }
        
        $this->products = $query->orderBy($this->sort)->get();
    }
}
```

### Multi-step Form

```blade
<div>
    @if($step === 1)
        <div>
            <h3>Step 1: Personal Info</h3>
            <input diff:model="name" placeholder="Name">
            <input diff:model="email" placeholder="Email">
            <button diff:click="nextStep">Next</button>
        </div>
    @elseif($step === 2)
        <div>
            <h3>Step 2: Address</h3>
            <input diff:model="address" placeholder="Address">
            <input diff:model="city" placeholder="City">
            <button diff:click="previousStep">Back</button>
            <button diff:click="nextStep">Next</button>
        </div>
    @else
        <div>
            <h3>Step 3: Review</h3>
            <p>Name: {{ $name }}</p>
            <p>Email: {{ $email }}</p>
            <p>Address: {{ $address }}, {{ $city }}</p>
            <button diff:click="previousStep">Back</button>
            <button diff:click="submit">Submit</button>
        </div>
    @endif
</div>
```

Component:

```php
class MultiStepForm extends Component
{
    public int $step = 1;
    public string $name = '';
    public string $email = '';
    public string $address = '';
    public string $city = '';
    
    public function nextStep()
    {
        $this->step++;
    }
    
    public function previousStep()
    {
        $this->step--;
    }
    
    public function submit()
    {
        // Submit form...
    }
}
```

## Best Practices

### 1. Choose the Right Modifier

- **Real-time search:** `diffyne:model.live.debounce.300`
- **Form inputs:** `diffyne:model` (updates local state, syncs on change)
- **Immediate server validation:** `diffyne:model.lazy`

### 2. Type Your Properties

```php
// Good - Type hints ensure data integrity
public string $name = '';
public int $age = 0;
public bool $active = false;
public array $tags = [];

// Avoid - Untyped properties can cause issues
public $name;
public $age;
```

### 3. Validate User Input

```php
protected function rules(): array
{
    return [
        'email' => 'required|email',
        'age' => 'required|integer|min:18',
    ];
}
```

### 4. Use Lifecycle Hooks

```php
public function updated(string $property)
{
    if ($property === 'search') {
        $this->performSearch();
    }
}
```

### 5. Initialize in mount()

```php
public function mount()
{
    $this->email = auth()->user()->email;
}
```

## Performance Tips

1. **Use default model for forms**: Only use `.live` when needed
2. **Add `.debounce` to live search**: Prevents excessive requests
3. **Lazy load data**: Don't fetch data until needed
4. **Optimize `updated()` hook**: Only react to relevant properties

## Troubleshooting

### Model Not Updating

Check property is public:

```php
// Wrong
private string $name;

// Correct
public string $name;
```

### Type Errors

Ensure property type matches input:

```php
// Checkbox needs bool
public bool $active = false;

// Number input needs int/float
public int $age = 0;
```

### Lost Input Value

Make sure property has default value:

```php
// Good
public string $name = '';

// May cause issues
public string $name;
```

## Next Steps

- [Forms](forms.md) - Form handling and submission
- [Validation](validation.md) - Validate user input
- [Loading States](loading-states.md) - Show loading feedback
- [Contact Form Example](../examples/contact-form.md)
