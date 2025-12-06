# Contact Form Example

A complete contact form with validation demonstrating form handling and error display.

## Component Code

### PHP Class

`app/Diffyne/ContactForm.php`:

```php
<?php

namespace App\Diffyne;

use Diffyne\Component;

class ContactForm extends Component
{
    public string $name = '';
    public string $email = '';
    public string $message = '';
    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|min:3|max:255',
            'email' => 'required|email',
            'message' => 'required|min:10',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Please enter your name',
            'name.min' => 'Name must be at least 3 characters',
            'email.required' => 'Email address is required',
            'email.email' => 'Please enter a valid email address',
            'message.required' => 'Message cannot be empty',
            'message.min' => 'Message must be at least 10 characters',
        ];
    }

    public function submit()
    {
        // Validate all fields
        $validated = $this->validate();

        // Process the form
        // For example: Send email, save to database, etc.
        // Mail::to('contact@example.com')->send(new ContactMessage($validated));
        
        // Mark as submitted
        $this->submitted = true;
        
        // Reset form
        $this->reset('name', 'email', 'message');
    }
}
```

### Blade View

`resources/views/diffyne/contact-form.blade.php`:

```blade
<div class="max-w-md mx-auto mt-8 p-6 bg-white rounded-lg shadow-lg">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Contact Us</h2>

    @if ($submitted)
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-green-800">Thank you! Your message has been sent successfully.</p>
        </div>
    @endif

    <form diff:submit.prevent="submit">
        {{-- Name Field --}}
        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                Name
            </label>
            <input 
                type="text" 
                id="name"
                diff:model.defer="name"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Your name"
            >
            <span diff:error="name" class="text-red-500 text-sm mt-1 block"></span>
        </div>

        {{-- Email Field --}}
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                Email
            </label>
            <input 
                type="email" 
                id="email"
                diff:model.defer="email"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="your.email@example.com"
            >
            <span diff:error="email" class="text-red-500 text-sm mt-1 block"></span>
        </div>

        {{-- Message Field --}}
        <div class="mb-6">
            <label for="message" class="block text-sm font-medium text-gray-700 mb-1">
                Message
            </label>
            <textarea 
                id="message"
                diff:model.defer="message"
                rows="4"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Your message here..."
            ></textarea>
            <span diff:error="message" class="text-red-500 text-sm mt-1 block"></span>
        </div>

        {{-- Submit Button --}}
        <button 
            type="submit"
            diff:loading.class="opacity-50 cursor-not-allowed"
            diff:loading.attr="disabled"
            class="w-full px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition font-medium">
            <span diff:loading.remove>Send Message</span>
            <span diff:loading>Sending...</span>
        </button>
    </form>
</div>
```

### Usage

```blade
<diffyne:contact-form />
```

## How It Works

### 1. Validation Rules

```php
protected function rules(): array
{
    return [
        'name' => 'required|min:3|max:255',
        'email' => 'required|email',
        'message' => 'required|min:10',
    ];
}
```

Define Laravel validation rules for each field.

### 2. Custom Error Messages

```php
protected function messages(): array
{
    return [
        'name.required' => 'Please enter your name',
        'email.email' => 'Please enter a valid email address',
        // ...
    ];
}
```

Provide user-friendly error messages.

### 3. Automatic Error Display

```blade
<input diff:model.defer="name">
<span diff:error="name" class="text-red-500"></span>
```

The `diff:error` attribute automatically:
- Shows error message when validation fails
- Hides when field becomes valid
- Adds `diffyne-error` class to the input

### 4. Deferred Binding

```blade
<input diff:model.defer="name">
```

Using `.defer` means:
- Input values sync only when form is submitted
- Reduces server requests (no sync on every keystroke)
- Better performance for multi-field forms

### 5. Loading State

```blade
<button 
    diff:loading.class="opacity-50 cursor-not-allowed"
    diff:loading.attr="disabled">
    <span diff:loading.remove>Send Message</span>
    <span diff:loading>Sending...</span>
</button>
```

While form submits:
- Button becomes disabled and semi-transparent
- Text changes from "Send Message" to "Sending..."

## Data Flow

```
User fills form and clicks "Send Message"
    ↓
Form submission captured by diff:submit.prevent
    ↓
AJAX request: {
    method: 'submit',
    state: {name: 'John', email: 'john@example.com', message: 'Hello!'}
}
    ↓
Server: ContactForm component hydrated
    ↓
Server: submit() method called
    ↓
Server: validate() runs Laravel validation
    ↓
Validation passes ✓
    ↓
Server: Email sent, $submitted = true
    ↓
Server: Fields reset
    ↓
Server: View re-rendered
    ↓
Response: {
    patches: [
        {type: 'show', selector: '.success-message'},
        {type: 'attr', node: '#name', attr: 'value', value: ''},
        {type: 'attr', node: '#email', attr: 'value', value: ''},
        {type: 'attr', node: '#message', attr: 'value', value: ''}
    ],
    state: {name: '', email: '', message: '', submitted: true}
}
    ↓
Browser: Success message shown, form cleared
```

## If Validation Fails

```
User submits incomplete form
    ↓
AJAX request with partial data
    ↓
Server: validate() throws ValidationException
    ↓
Response (422): {
    success: false,
    type: 'validation_error',
    errors: {
        name: ['Name must be at least 3 characters'],
        email: ['Please enter a valid email address']
    }
}
    ↓
Browser: displayErrors() called
    ↓
For each error:
  - Find input field
  - Add 'diffyne-error' class
  - Find diff:error span
  - Insert error message
    ↓
UI: Errors appear next to invalid fields
```

## Key Concepts

### Validation Integration

Diffyne integrates seamlessly with Laravel's validator:

```php
public function submit()
{
    // This throws ValidationException on failure
    $validated = $this->validate();
    
    // Only runs if validation passes
    $this->processForm($validated);
}
```

### Field-Level Validation

Validate single fields:

```php
public function updated($field)
{
    if ($field === 'email') {
        $this->validateOnly('email');
    }
}
```

### Manual Errors

Add custom errors:

```php
public function submit()
{
    $this->validate();
    
    // Check if email is blacklisted
    if ($this->isBlacklisted($this->email)) {
        $this->addError('email', 'This email address is not allowed');
        return;
    }
    
    // Continue...
}
```

## Enhancements

### Add reCAPTCHA

```blade
<form diff:submit.prevent="submit">
    {{-- Form fields --}}
    
    <div class="g-recaptcha" data-sitekey="your-site-key"></div>
    
    <button type="submit">Send</button>
</form>
```

```php
protected function rules(): array
{
    return [
        'name' => 'required|min:3',
        'email' => 'required|email',
        'message' => 'required|min:10',
        'g-recaptcha-response' => 'required|recaptcha',
    ];
}
```

### Save to Database

```php
use App\Models\ContactMessage;

public function submit()
{
    $validated = $this->validate();
    
    ContactMessage::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'message' => $validated['message'],
        'ip_address' => request()->ip(),
    ]);
    
    $this->submitted = true;
    $this->reset('name', 'email', 'message');
}
```

### Send Email

```php
use App\Mail\ContactFormSubmission;
use Illuminate\Support\Facades\Mail;

public function submit()
{
    $validated = $this->validate();
    
    Mail::to('contact@example.com')
        ->send(new ContactFormSubmission($validated));
    
    $this->submitted = true;
    $this->reset('name', 'email', 'message');
}
```

### Add File Upload

```php
public string $name = '';
public string $email = '';
public string $message = '';
public string $attachment = '';

protected function rules(): array
{
    return [
        'name' => 'required|min:3',
        'email' => 'required|email',
        'message' => 'required|min:10',
        'attachment' => 'nullable|file|max:5120', // 5MB max
    ];
}
```

```blade
<input 
    type="file" 
    id="attachment"
    onchange="document.getElementById('attachmentName').value = this.files[0]?.name || ''">

<input 
    type="hidden" 
    id="attachmentName"
    diff:model.defer="attachment">
```

### Add Subject Selection

```php
public string $subject = 'general';

protected function rules(): array
{
    return [
        'subject' => 'required|in:general,support,sales,feedback',
        'name' => 'required|min:3',
        // ...
    ];
}
```

```blade
<select diff:model.defer="subject">
    <option value="general">General Inquiry</option>
    <option value="support">Technical Support</option>
    <option value="sales">Sales</option>
    <option value="feedback">Feedback</option>
</select>
```

## Best Practices

### 1. Always Validate on Server

```php
public function submit()
{
    // Server-side validation is mandatory
    $this->validate();
    
    // Process form...
}
```

### 2. Provide Clear Feedback

```blade
@if ($submitted)
    <div class="success-message">
        Thank you! We'll get back to you soon.
    </div>
@endif
```

### 3. Use Deferred Binding for Forms

```blade
{{-- Efficient - syncs once on submit --}}
<input diff:model.defer="name">
```

### 4. Show Loading States

```blade
<button diff:loading.attr="disabled">
    <span diff:loading.remove>Send</span>
    <span diff:loading>Sending...</span>
</button>
```

### 5. Reset After Success

```php
public function submit()
{
    $this->validate();
    // Process...
    $this->submitted = true;
    $this->reset('name', 'email', 'message');
}
```

## Next Steps

- [Validation](../features/validation.md) - Detailed validation guide
- [Forms](../features/forms.md) - Form handling
- [Data Binding](../features/data-binding.md) - Model binding
- [Search Example](search.md) - Live search with validation
