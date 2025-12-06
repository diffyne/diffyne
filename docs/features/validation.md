# Validation

Diffyne integrates seamlessly with Laravel's validation system, providing automatic error display and field highlighting.

## Basic Validation

### Define Rules

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
        $this->validate(); // Throws ValidationException on failure
        
        // Process form...
    }
}
```

### Display Errors

```blade
<form diff:submit.prevent="submit">
    <div>
        <input diff:model.defer="name">
        <span diff:error="name" class="text-red-500"></span>
    </div>
    
    <div>
        <input type="email" diff:model.defer="email">
        <span diff:error="email" class="text-red-500"></span>
    </div>
    
    <div>
        <textarea diff:model.defer="message"></textarea>
        <span diff:error="message" class="text-red-500"></span>
    </div>
    
    <button type="submit">Submit</button>
</form>
```

The `diff:error` attribute automatically:
- Shows error message when validation fails
- Hides when field becomes valid
- Adds `diffyne-error` class to the input field

## Custom Error Messages

```php
protected function messages(): array
{
    return [
        'name.required' => 'Please enter your name',
        'name.min' => 'Name must be at least 3 characters',
        'email.required' => 'Email address is required',
        'email.email' => 'Please enter a valid email address',
        'message.required' => 'Please enter a message',
        'message.min' => 'Message must be at least 10 characters',
    ];
}
```

## Custom Attribute Names

```php
protected function validationAttributes(): array
{
    return [
        'email' => 'email address',
        'message' => 'message content',
    ];
}
```

Error messages will use these names: "The email address must be valid" instead of "The email must be valid".

## Validation Methods

### validate()

Validates all properties defined in `rules()`:

```php
public function submit()
{
    $validated = $this->validate();
    // $validated contains only validated data
    
    // Use validated data
    Mail::send($validated);
}
```

### validateOnly()

Validates a single field:

```php
public function updated($field)
{
    if ($field === 'email') {
        $this->validateOnly('email');
    }
}
```

### resetValidation()

Clears all validation errors:

```php
public function clearForm()
{
    $this->reset();
    $this->resetValidation();
}
```

Clear specific field errors:

```php
$this->resetValidation('email');
$this->resetValidation(['email', 'name']);
```

### addError()

Manually add validation errors:

```php
public function checkAvailability()
{
    if (User::where('email', $this->email)->exists()) {
        $this->addError('email', 'This email is already taken');
    }
}
```

## Real-time Validation

Validate fields as user types or on blur:

```blade
<input 
    diff:model.lazy="email"
    diff:change="validateEmail">
<span diff:error="email"></span>
```

Component:

```php
public function validateEmail()
{
    $this->validateOnly('email');
}

protected function rules(): array
{
    return [
        'email' => 'required|email|unique:users',
    ];
}
```

## Complex Validation Rules

### Conditional Rules

```php
protected function rules(): array
{
    return [
        'email' => 'required|email',
        'phone' => $this->contactMethod === 'phone' ? 'required' : '',
        'company' => $this->type === 'business' ? 'required' : '',
    ];
}
```

### Dynamic Rules

```php
protected function rules(): array
{
    $rules = [
        'name' => 'required|min:3',
        'email' => 'required|email',
    ];
    
    if ($this->updatePassword) {
        $rules['password'] = 'required|min:8|confirmed';
    }
    
    return $rules;
}
```

### Array Validation

```php
public array $emails = [''];

protected function rules(): array
{
    return [
        'emails' => 'required|array|min:1',
        'emails.*' => 'required|email',
    ];
}
```

### Nested Object Validation

```php
public array $address = [
    'street' => '',
    'city' => '',
    'zip' => '',
];

protected function rules(): array
{
    return [
        'address.street' => 'required',
        'address.city' => 'required',
        'address.zip' => 'required|numeric',
    ];
}
```

## Validation with Database Checks

### Unique Validation

```php
protected function rules(): array
{
    return [
        'email' => 'required|email|unique:users,email',
        'username' => 'required|unique:users,username',
    ];
}
```

Ignore current user when updating:

```php
protected function rules(): array
{
    return [
        'email' => ['required', 'email', Rule::unique('users')->ignore($this->userId)],
    ];
}
```

### Exists Validation

```php
protected function rules(): array
{
    return [
        'category_id' => 'required|exists:categories,id',
        'user_id' => 'required|exists:users,id',
    ];
}
```

## Custom Validation Rules

### Inline Closure

```php
use Illuminate\Validation\Rule;

protected function rules(): array
{
    return [
        'coupon' => [
            'required',
            function ($attribute, $value, $fail) {
                if (!$this->isValidCoupon($value)) {
                    $fail('The coupon code is invalid or expired.');
                }
            },
        ],
    ];
}

private function isValidCoupon($code): bool
{
    return Coupon::where('code', $code)
        ->where('expires_at', '>', now())
        ->exists();
}
```

### Custom Rule Class

```php
use App\Rules\ValidCoupon;

protected function rules(): array
{
    return [
        'coupon' => ['required', new ValidCoupon()],
    ];
}
```

## Error Styling

### Automatic Field Highlighting

When validation fails, Diffyne automatically adds `diffyne-error` class to input fields. Style them in your CSS:

```css
.diffyne-error {
    border-color: #ef4444;
    outline: none;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}
```

### Manual Field Styling

Use Blade directives for conditional classes:

```blade
<input 
    diff:model.defer="email"
    class="border rounded px-3 py-2 
           @error('email') border-red-500 @enderror">
```

### Error Message Container

```blade
<div>
    <input diff:model.defer="email">
    
    {{-- Diffyne automatic error --}}
    <span diff:error="email" class="text-red-500 text-sm"></span>
    
    {{-- Or manual Blade error --}}
    @error('email')
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>
```

## Complete Example

```blade
<form diff:submit.prevent="register">
    {{-- Name Field --}}
    <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Name</label>
        <input 
            type="text"
            diff:model.defer="name"
            class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2">
        <span diff:error="name" class="text-red-500 text-sm mt-1 block"></span>
    </div>
    
    {{-- Email Field --}}
    <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Email</label>
        <input 
            type="email"
            diff:model.defer="email"
            class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2">
        <span diff:error="email" class="text-red-500 text-sm mt-1 block"></span>
    </div>
    
    {{-- Password Field --}}
    <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Password</label>
        <input 
            type="password"
            diff:model.defer="password"
            class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2">
        <span diff:error="password" class="text-red-500 text-sm mt-1 block"></span>
    </div>
    
    {{-- Password Confirmation --}}
    <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Confirm Password</label>
        <input 
            type="password"
            diff:model.defer="password_confirmation"
            class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2">
        <span diff:error="password_confirmation" class="text-red-500 text-sm mt-1 block"></span>
    </div>
    
    {{-- Submit Button --}}
    <button 
        type="submit"
        diff:loading.class="opacity-50 cursor-not-allowed"
        diff:loading.attr="disabled"
        class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
        <span diff:loading.remove>Register</span>
        <span diff:loading>Registering...</span>
    </button>
</form>

@if($registered)
    <div class="mt-4 p-4 bg-green-100 text-green-700 rounded">
        Registration successful!
    </div>
@endif
```

Component:

```php
<?php

namespace App\Diffyne;

use App\Models\User;
use Diffyne\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterForm extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $registered = false;
    
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
    
    protected function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name',
            'name.min' => 'Name must be at least 3 characters',
            'email.required' => 'Email address is required',
            'email.email' => 'Please enter a valid email address',
            'email.unique' => 'This email is already registered',
            'password.required' => 'Password is required',
            'password.confirmed' => 'Passwords do not match',
        ];
    }
    
    public function register()
    {
        $validated = $this->validate();
        
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        
        auth()->login($user);
        
        $this->registered = true;
        
        // Optionally redirect
        // return redirect('/dashboard');
    }
}
```

## Best Practices

### 1. Always Validate on Server

Never trust client-side validation alone:

```php
public function submit()
{
    // Server-side validation is mandatory
    $this->validate();
    
    // Process data...
}
```

### 2. Use Type-safe Properties

```php
// Good
public string $email = '';
public int $age = 0;
public bool $active = false;

// Avoid
public $email;
public $age;
```

### 3. Provide Clear Error Messages

```php
protected function messages(): array
{
    return [
        'email.email' => 'Please enter a valid email address',
        'password.min' => 'Password must be at least 8 characters long',
    ];
}
```

### 4. Use diff:error for Automatic Display

```blade
{{-- Preferred - automatic error display --}}
<span diff:error="email"></span>

{{-- Also works but less reactive --}}
@error('email')
    <span>{{ $message }}</span>
@enderror
```

### 5. Reset Form After Success

```php
public function submit()
{
    $this->validate();
    
    // Process form...
    
    // Clear form
    $this->reset();
    $this->resetValidation();
}
```

## Next Steps

- [Forms](forms.md) - Form handling
- [Data Binding](data-binding.md) - Two-way data sync
- [Contact Form Example](../examples/contact-form.md) - Complete example
- [Error Handling](error-handling.md) - Handle errors gracefully
