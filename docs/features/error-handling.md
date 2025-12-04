# Error Handling

Diffyne provides automatic error handling with graceful fallbacks and user feedback.

## Automatic Error Display

### Validation Errors

Validation errors are automatically caught and displayed:

```blade
<form diff:submit.prevent="submit">
    <input diff:model.defer="email">
    <span diff:error="email" class="text-red-500"></span>
    
    <button type="submit">Submit</button>
</form>
```

When validation fails, errors automatically appear in `diffyne:error` elements.

### Server Errors

Server errors are caught and displayed via browser alert by default:

```php
public function save()
{
    // If this throws an exception
    throw new \Exception('Something went wrong');
    
    // User sees alert: "Something went wrong"
}
```

## Manual Error Handling

### Add Custom Errors

```php
public function checkAvailability()
{
    if (!$this->isAvailable()) {
        $this->addError('username', 'This username is already taken');
        return;
    }
    
    // Continue...
}
```

### Reset Errors

```php
public function clearErrors()
{
    $this->resetValidation();
}

public function clearFieldError()
{
    $this->resetValidation('email');
}
```

## Error Types

Diffyne handles different error types:

### 1. Validation Errors (422)

```php
public function submit()
{
    $this->validate(); // Throws ValidationException
    
    // Errors automatically displayed in diffyne:error elements
}
```

### 2. Server Errors (500)

```php
public function process()
{
    try {
        // Risky operation
        $this->processPayment();
    } catch (\Exception $e) {
        // Log error
        logger()->error('Payment failed', [
            'user' => auth()->id(),
            'error' => $e->getMessage()
        ]);
        
        // Show user-friendly message
        $this->addError('general', 'Payment processing failed. Please try again.');
    }
}
```

### 3. Authorization Errors (403)

```php
public function delete()
{
    if (!auth()->user()->can('delete', $this->post)) {
        $this->addError('general', 'You are not authorized to delete this post');
        return;
    }
    
    $this->post->delete();
}
```

## Custom Error Messages

### General Errors

```blade
<div>
    {{-- Show general errors --}}
    @if($errors->has('general'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ $errors->first('general') }}
        </div>
    @endif
    
    {{-- Your component content --}}
</div>
```

Component:

```php
public function process()
{
    if (!$this->canProcess()) {
        $this->addError('general', 'Unable to process request');
        return;
    }
}
```

### Field-Specific Errors

```blade
<div>
    <input diff:model="email">
    
    {{-- Automatic error display --}}
    <span diff:error="email" class="text-red-500"></span>
    
    {{-- Or manual display --}}
    @error('email')
        <span class="text-red-500">{{ $message }}</span>
    @enderror
</div>
```

## Error Styling

### Automatic Field Highlighting

Diffyne adds `diffyne-error` class to fields with errors:

```css
/* Add to your CSS */
.diffyne-error {
    border-color: #ef4444;
    outline: none;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}
```

### Manual Styling

```blade
<input 
    diff:model="email"
    class="border rounded px-3 py-2
           @error('email') border-red-500 @enderror">
```

## Complete Error Handling Example

```blade
<div class="max-w-md mx-auto">
    {{-- General error message --}}
    @if($errors->has('general'))
        <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p class="font-bold">Error</p>
            <p>{{ $errors->first('general') }}</p>
        </div>
    @endif
    
    {{-- Success message --}}
    @if($success)
        <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
            <p class="font-bold">Success</p>
            <p>Your form was submitted successfully!</p>
        </div>
    @endif
    
    <form diff:submit.prevent="submit">
        {{-- Name field --}}
        <div class="mb-4">
            <label class="block text-gray-700 font-bold mb-2">Name</label>
            <input 
                type="text"
                diff:model.defer="name"
                class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2">
            <span diff:error="name" class="text-red-500 text-sm mt-1 block"></span>
        </div>
        
        {{-- Email field --}}
        <div class="mb-4">
            <label class="block text-gray-700 font-bold mb-2">Email</label>
            <input 
                type="email"
                diff:model.defer="email"
                class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2">
            <span diff:error="email" class="text-red-500 text-sm mt-1 block"></span>
        </div>
        
        {{-- Submit button --}}
        <button 
            type="submit"
            diff:loading.class="opacity-50 cursor-not-allowed"
            diff:loading.attr="disabled"
            class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
            <span diffyne:loading.remove>Submit</span>
            <span diffyne:loading>Submitting...</span>
        </button>
    </form>
</div>
```

Component:

```php
<?php

namespace App\Diffyne;

use Diffyne\Component;
use Illuminate\Support\Facades\Mail;

class ContactForm extends Component
{
    public string $name = '';
    public string $email = '';
    public bool $success = false;
    
    protected function rules(): array
    {
        return [
            'name' => 'required|min:3',
            'email' => 'required|email',
        ];
    }
    
    protected function messages(): array
    {
        return [
            'name.required' => 'Please enter your name',
            'name.min' => 'Name must be at least 3 characters',
            'email.required' => 'Please enter your email',
            'email.email' => 'Please enter a valid email address',
        ];
    }
    
    public function submit()
    {
        try {
            // Validate input
            $validated = $this->validate();
            
            // Check for additional business rules
            if ($this->isBlacklisted($validated['email'])) {
                $this->addError('email', 'This email address is not allowed');
                return;
            }
            
            // Send email
            Mail::to('admin@example.com')->send(new ContactMessage($validated));
            
            // Show success
            $this->success = true;
            
            // Reset form
            $this->reset('name', 'email');
            $this->resetValidation();
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation errors are automatically handled
            throw $e;
            
        } catch (\Exception $e) {
            // Log unexpected errors
            logger()->error('Contact form error', [
                'user_input' => ['name' => $this->name, 'email' => $this->email],
                'error' => $e->getMessage(),
            ]);
            
            // Show user-friendly message
            $this->addError('general', 'An error occurred. Please try again later.');
        }
    }
    
    private function isBlacklisted(string $email): bool
    {
        return in_array($email, ['spam@example.com', 'blocked@example.com']);
    }
}
```

## Error Recovery

### Retry Logic

```php
public function processPayment()
{
    $attempts = 0;
    $maxAttempts = 3;
    
    while ($attempts < $maxAttempts) {
        try {
            $this->charge();
            $this->success = true;
            return;
            
        } catch (\Exception $e) {
            $attempts++;
            
            if ($attempts >= $maxAttempts) {
                $this->addError('general', 'Payment failed after multiple attempts');
                logger()->error('Payment failed', ['error' => $e->getMessage()]);
                return;
            }
            
            sleep(1); // Wait before retry
        }
    }
}
```

### Graceful Degradation

```php
public function loadData()
{
    try {
        // Try primary data source
        $this->data = $this->fetchFromApi();
        
    } catch (\Exception $e) {
        try {
            // Fallback to cache
            $this->data = Cache::get('data_backup');
            $this->addError('general', 'Using cached data due to connection issues');
            
        } catch (\Exception $e) {
            // Final fallback
            $this->data = [];
            $this->addError('general', 'Unable to load data');
        }
    }
}
```

## Best Practices

### 1. Always Validate User Input

```php
public function save()
{
    // Always validate
    $this->validate();
    
    // Process data...
}
```

### 2. Use Try-Catch for Risky Operations

```php
public function process()
{
    try {
        // Risky operation
        $this->externalApiCall();
    } catch (\Exception $e) {
        logger()->error('API call failed', ['error' => $e->getMessage()]);
        $this->addError('general', 'Service temporarily unavailable');
    }
}
```

### 3. Provide User-Friendly Messages

```php
// Good
$this->addError('email', 'This email is already registered');

// Avoid technical jargon
$this->addError('email', 'SQLSTATE[23000]: Integrity constraint violation');
```

### 4. Log Errors for Debugging

```php
try {
    $this->process();
} catch (\Exception $e) {
    // Log full error details
    logger()->error('Processing failed', [
        'component' => static::class,
        'user' => auth()->id(),
        'data' => $this->getState(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    // Show simple message to user
    $this->addError('general', 'An error occurred');
}
```

### 5. Reset Errors Appropriately

```php
public function updated($field)
{
    // Clear error for specific field when it changes
    $this->resetValidation($field);
}

public function submit()
{
    // Clear all errors before revalidating
    $this->resetValidation();
    $this->validate();
}
```

## Debugging

### Enable Debug Mode

In `config/diffyne.php`:

```php
return [
    'debug' => env('DIFFYNE_DEBUG', false),
];
```

Set in `.env`:

```env
DIFFYNE_DEBUG=true
```

### Check Browser Console

Open browser console (F12) to see:
- AJAX requests/responses
- Validation errors
- Server errors
- State changes

### Server Logs

Check Laravel logs:

```bash
tail -f storage/logs/laravel.log
```

## Next Steps

- [Validation](validation.md) - Form validation details
- [Forms](forms.md) - Form handling
- [Testing](../advanced/testing.md) - Test error scenarios
- [Examples](../examples/) - Error handling examples
