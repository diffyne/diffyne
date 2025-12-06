# Form Handling

Handle form submissions with `diff:submit` directive.

## Basic Form Submission

```blade
<form diff:submit="submit">
    <input type="text" diff:model="name">
    <button type="submit">Submit</button>
</form>
```

Component:

```php
use Diffyne\Attributes\Invokable;

class ContactForm extends Component
{
    public string $name = '';
    
    #[Invokable]
    public function submit()
    {
        // Handle form submission
        logger('Form submitted:', ['name' => $this->name]);
    }
}
```

**Note:** Form default submission is automatically prevented when using `diff:submit`.

## Form with Validation

```blade
<form diff:submit="submit">
    <div>
        <label>Email</label>
        <input 
            type="email" 
            diff:model="email"
            class="border rounded px-3 py-2">
        <span diff:error="email" class="text-red-500"></span>
    </div>
    
    <div>
        <label>Password</label>
        <input 
            type="password" 
            diff:model="password"
            class="border rounded px-3 py-2">
        <span diff:error="password" class="text-red-500"></span>
    </div>
    
    <button 
        type="submit"
        diff:loading.class.opacity-50
        class="bg-blue-500 text-white px-4 py-2 rounded">
        Login
        <span diff:loading>...</span>
    </button>
</form>

@if(session('success'))
    <div class="text-green-500">{{ session('success') }}</div>
@endif
```

Component:

```php
use Diffyne\Attributes\Invokable;

class LoginForm extends Component
{
    public string $email = '';
    public string $password = '';
    
    protected function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ];
    }
    
    protected function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address',
            'email.email' => 'Please enter a valid email',
            'password.min' => 'Password must be at least 8 characters',
        ];
    }
    
    #[Invokable]
    public function submit()
    {
        $validated = $this->validate();
        
        if (auth()->attempt($validated)) {
            session()->flash('success', 'Logged in successfully!');
            $this->redirect('/dashboard');
        }
        
        $this->addError('email', 'Invalid credentials');
    }
}
```

## Model Binding in Forms

By default, `diff:model` updates local state and syncs with server on change events. For forms, this is typically what you want:

```blade
<form diff:submit="createUser">
    <input diff:model="name">
    <input diff:model="email">
    <input diff:model="phone">
    <button type="submit">Create User</button>
</form>
```

**Note:** Inputs update local state as you type, and send server requests on change/blur events.

## Multi-field Validation

```blade
<form diff:submit="register">
    <div>
        <input diff:model="username" placeholder="Username">
        <span diff:error="username"></span>
    </div>
    
    <div>
        <input type="email" diff:model="email" placeholder="Email">
        <span diff:error="email"></span>
    </div>
    
    <div>
        <input type="password" diff:model="password" placeholder="Password">
        <span diff:error="password"></span>
    </div>
    
    <div>
        <input type="password" diff:model="passwordConfirmation" placeholder="Confirm Password">
        <span diff:error="passwordConfirmation"></span>
    </div>
    
    <button type="submit">Register</button>
</form>
```

Component:

```php
use App\Models\User;

class RegisterForm extends Component
{
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $passwordConfirmation = '';
    
    protected function rules(): array
    {
        return [
            'username' => 'required|min:3|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ];
    }
    
    public function register()
    {
        $validated = $this->validate();
        
        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);
        
        auth()->login($user);
        
        $this->redirect('/dashboard');
    }
}
```

## Form Reset

Reset form after successful submission:

```blade
<form diff:submit="submit">
    <input diff:model="message">
    <button type="submit">Send</button>
</form>

@if($sent)
    <div class="text-green-500">Message sent!</div>
@endif
```

Component:

```php
use Diffyne\Attributes\Invokable;

class MessageForm extends Component
{
    public string $message = '';
    public bool $sent = false;
    
    #[Invokable]
    public function submit()
    {
        $this->validate(['message' => 'required|min:10']);
        
        // Send message...
        
        $this->sent = true;
        $this->reset('message'); // Clear the input
        
        // Or reset all properties
        // $this->reset();
    }
}
```

## Real-time Field Validation

Validate individual fields as user types:

```blade
<form diff:submit="submit">
    <div>
        <input 
            type="email" 
            diff:model.lazy="email"
            diff:change="validateEmail">
        <span diff:error="email"></span>
    </div>
    
    <button type="submit">Submit</button>
</form>
```

Component:

```php
use Diffyne\Attributes\Invokable;

class SignupForm extends Component
{
    public string $email = '';
    
    #[Invokable]
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
    
    #[Invokable]
    public function submit()
    {
        $this->validate();
        // Process signup...
    }
}
```

## Complex Forms

### Dynamic Fields

```blade
<form diff:submit="submit">
    @foreach($emails as $index => $email)
        <div>
            <input 
                diff:model="emails.{{ $index }}"
                placeholder="Email {{ $index + 1 }}">
            <button 
                type="button"
                diff:click="removeEmail({{ $index }})">
                Remove
            </button>
        </div>
    @endforeach
    
    <button type="button" diff:click="addEmail">Add Email</button>
    <button type="submit">Submit</button>
</form>
```

Component:

```php
use Diffyne\Attributes\Invokable;

class MultiEmailForm extends Component
{
    public array $emails = [''];
    
    #[Invokable]
    public function addEmail()
    {
        $this->emails[] = '';
    }
    
    #[Invokable]
    public function removeEmail($index)
    {
        unset($this->emails[$index]);
        $this->emails = array_values($this->emails);
    }
    
    #[Invokable]
    public function submit()
    {
        $this->validate([
            'emails.*' => 'required|email',
        ]);
        
        // Process emails...
    }
}
```

### File Upload

```blade
<form diff:submit="uploadFile">
    <input 
        type="file" 
        id="fileInput"
        onchange="document.getElementById('fileName').value = this.files[0]?.name || ''">
    
    <input 
        type="hidden" 
        id="fileName"
        diff:model="fileName">
    
    <button type="submit">Upload</button>
</form>
```

Component:

```php
class FileUpload extends Component
{
    public string $fileName = '';
    
    public function uploadFile()
    {
        $this->validate(['fileName' => 'required']);
        
        if (request()->hasFile('file')) {
            $path = request()->file('file')->store('uploads');
            $this->fileName = $path;
        }
    }
}
```

### Conditional Fields

```blade
<form diff:submit="submit">
    <select diff:model.live="type">
        <option value="individual">Individual</option>
        <option value="business">Business</option>
    </select>
    
    @if($type === 'individual')
        <input diff:model="firstName" placeholder="First Name">
        <input diff:model="lastName" placeholder="Last Name">
    @else
        <input diff:model="companyName" placeholder="Company Name">
        <input diff:model="taxId" placeholder="Tax ID">
    @endif
    
    <button type="submit">Submit</button>
</form>
```

Component:

```php
class DynamicForm extends Component
{
    public string $type = 'individual';
    public string $firstName = '';
    public string $lastName = '';
    public string $companyName = '';
    public string $taxId = '';
    
    protected function rules(): array
    {
        if ($this->type === 'individual') {
            return [
                'firstName' => 'required|min:2',
                'lastName' => 'required|min:2',
            ];
        }
        
        return [
            'companyName' => 'required|min:3',
            'taxId' => 'required',
        ];
    }
    
    #[Invokable]
    public function submit()
    {
        $this->validate();
        // Process form...
    }
}
```

## Loading States

Show feedback during form submission:

```blade
<form diff:submit="submit">
    <input diff:model="email">
    
    <button 
        type="submit"
        diff:loading.class.opacity-50.cursor-not-allowed>
        <span diff:loading>Submitting...</span>
        Submit
    </button>
</form>

<div diff:loading class="text-blue-500">
    Processing your request...
</div>
```

## Best Practices

### 1. Form Submission is Prevented Automatically

```blade
{{-- Good - default behavior prevents reload --}}
<form diff:submit="submit">
```

### 2. Choose Model Binding Strategy

```blade
{{-- Default: Updates local state, syncs on change --}}
<form diff:submit="submit">
    <input diff:model="field1">
    <input diff:model="field2">
</form>

{{-- Live updates: Immediate server sync --}}
<input diff:model.live="search">
```

### 3. Validate on Server

```php
use Diffyne\Attributes\Invokable;

#[Invokable]
public function submit()
{
    // Always validate server-side
    $this->validate();
    
    // Process form...
}
```

### 4. Provide User Feedback

```blade
<button type="submit" diff:loading.class.opacity-50>
    <span diff:loading>Submitting...</span>
    Submit
</button>

@if($success)
    <div class="text-green-500">Form submitted successfully!</div>
@endif
```

### 5. Handle Errors Gracefully

```php
use Diffyne\Attributes\Invokable;

#[Invokable]
public function submit()
{
    try {
        $this->validate();
        // Process form...
        $this->success = true;
    } catch (\Exception $e) {
        $this->addError('general', 'An error occurred. Please try again.');
    }
}
```

## Next Steps

- [Validation](validation.md) - Form validation in detail
- [Data Binding](data-binding.md) - Two-way data sync
- [Loading States](loading-states.md) - Better UX
- [Contact Form Example](../examples/contact-form.md)
