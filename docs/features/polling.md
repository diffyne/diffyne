# Polling

Automatically refresh component data at regular intervals with `diffyne:poll`.

## Basic Usage

```blade
<div diff:poll="5000" diff:poll.action="refresh">
    Last updated: {{ $lastUpdate }}
</div>
```

Calls the `refresh()` method every 5000 milliseconds (5 seconds).

Component:

```php
class Dashboard extends Component
{
    public string $lastUpdate;
    
    public function mount()
    {
        $this->refresh();
    }
    
    public function refresh()
    {
        $this->lastUpdate = now()->format('H:i:s');
    }
}
```

## Syntax

### With Action

```blade
{{-- Poll every 1 second (1000ms) --}}
<div diff:poll="1000" diff:poll.action="update">

{{-- Poll every 5 seconds (5000ms) --}}
<div diff:poll="5000" diff:poll.action="update">

{{-- Poll every 30 seconds (30000ms) --}}
<div diff:poll="30000" diff:poll.action="update">

{{-- Poll every 500 milliseconds --}}
<div diff:poll="500" diff:poll.action="update">
```

### Default Action

If `diffyne:poll.action` is not specified, it defaults to calling `refresh()`:

```blade
{{-- Calls refresh() every 2 seconds --}}
<div diff:poll="2000">
```

## Common Use Cases

### Real-time Dashboard

```blade
<div class="dashboard" diff:poll="5000" diff:poll.action="refreshStats">
    <div class="stat-card">
        <h3>Active Users</h3>
        <p class="text-3xl">{{ $activeUsers }}</p>
    </div>
    
    <div class="stat-card">
        <h3>Revenue Today</h3>
        <p class="text-3xl">${{ number_format($revenue, 2) }}</p>
    </div>
    
    <div class="stat-card">
        <h3>Orders</h3>
        <p class="text-3xl">{{ $orders }}</p>
    </div>
    
    <small class="text-gray-500">
        Last updated: {{ $lastUpdate }}
        <span diffyne:loading>Updating...</span>
    </small>
</div>
```

Component:

```php
use App\Models\Order;
use App\Models\User;

class Dashboard extends Component
{
    public int $activeUsers = 0;
    public float $revenue = 0;
    public int $orders = 0;
    public string $lastUpdate = '';
    
    public function mount()
    {
        $this->refreshStats();
    }
    
    public function refreshStats()
    {
        $this->activeUsers = User::where('last_active_at', '>', now()->subMinutes(5))->count();
        $this->revenue = Order::whereDate('created_at', today())->sum('total');
        $this->orders = Order::whereDate('created_at', today())->count();
        $this->lastUpdate = now()->format('H:i:s');
    }
}
```

### Notification Badge

```blade
<div class="relative">
    <button class="relative">
        <svg class="w-6 h-6"><!-- Bell icon --></svg>
        
        @if($unreadCount > 0)
            <span class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full px-2">
                {{ $unreadCount }}
            </span>
        @endif
    </button>
</div>

<div diff:poll="10000" diff:poll.action="checkNotifications" class="hidden"></div>
```

Component:

```php
use App\Models\Notification;

class NotificationBadge extends Component
{
    public int $unreadCount = 0;
    
    public function mount()
    {
        $this->checkNotifications();
    }
    
    public function checkNotifications()
    {
        $this->unreadCount = Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();
    }
}
```

### Order Status Tracker

```blade
<div diff:poll="3000" diff:poll.action="checkStatus">
    <div class="mb-4">
        <h2 class="text-2xl font-bold">Order #{{ $orderId }}</h2>
        <p class="text-gray-600">Status: <span class="font-semibold">{{ $status }}</span></p>
    </div>
    
    <div class="space-y-2">
        <div class="flex items-center {{ $status === 'pending' ? 'text-blue-500' : 'text-gray-400' }}">
            <span class="mr-2">●</span> Order Placed
        </div>
        <div class="flex items-center {{ $status === 'processing' ? 'text-blue-500' : 'text-gray-400' }}">
            <span class="mr-2">●</span> Processing
        </div>
        <div class="flex items-center {{ $status === 'shipped' ? 'text-blue-500' : 'text-gray-400' }}">
            <span class="mr-2">●</span> Shipped
        </div>
        <div class="flex items-center {{ $status === 'delivered' ? 'text-green-500' : 'text-gray-400' }}">
            <span class="mr-2">●</span> Delivered
        </div>
    </div>
    
    @if($status !== 'delivered')
        <p class="text-sm text-gray-500 mt-4">
            <span diffyne:loading>Checking for updates...</span>
        </p>
    @endif
</div>
```

Component:

```php
use App\Models\Order;

class OrderTracker extends Component
{
    public int $orderId;
    public string $status = '';
    
    public function mount($orderId)
    {
        $this->orderId = $orderId;
        $this->checkStatus();
    }
    
    public function checkStatus()
    {
        $order = Order::find($this->orderId);
        $this->status = $order->status;
    }
}
```

### Live Activity Feed

```blade
<div>
    <h3 class="text-xl font-bold mb-4">Recent Activity</h3>
    
    <div diff:poll="5000" diff:poll.action="refreshActivity" class="space-y-3">
        @foreach($activities as $activity)
            <div class="border-l-4 border-blue-500 pl-4 py-2">
                <p class="font-semibold">{{ $activity->user->name }}</p>
                <p class="text-sm text-gray-600">{{ $activity->description }}</p>
                <p class="text-xs text-gray-400">{{ $activity->created_at->diffForHumans() }}</p>
            </div>
        @endforeach
        
        @if(empty($activities))
            <p class="text-gray-500">No recent activity</p>
        @endif
    </div>
    
    <div diffyne:loading class="text-center text-gray-500 text-sm mt-2">
        Checking for new activity...
    </div>
</div>
```

Component:

```php
use App\Models\Activity;

class ActivityFeed extends Component
{
    public $activities = [];
    
    public function mount()
    {
        $this->refreshActivity();
    }
    
    public function refreshActivity()
    {
        $this->activities = Activity::with('user')
            ->latest()
            ->take(10)
            ->get();
    }
}
```

## Conditional Polling

Stop polling based on conditions:

```blade
<div @if($status !== 'completed') diff:poll="3000" diff:poll.action="checkProgress" @endif>
    <div class="progress-bar">
        <div style="width: {{ $progress }}%"></div>
    </div>
    
    <p>Progress: {{ $progress }}%</p>
    
    @if($status === 'completed')
        <p class="text-green-500">Complete!</p>
    @else
        <p diffyne:loading>Checking progress...</p>
    @endif
</div>
```

Component:

```php
class ProgressMonitor extends Component
{
    public int $progress = 0;
    public string $status = 'processing';
    
    public function checkProgress()
    {
        $job = ProcessingJob::find($this->jobId);
        
        $this->progress = $job->progress;
        $this->status = $job->status;
        
        // Polling will stop when status is 'completed'
    }
}
```

## Performance Considerations

### Efficient Queries

```php
// Bad - loads all data every poll
public function refresh()
{
    $this->items = Item::with('user', 'category', 'tags')->get();
}

// Good - only fetch what changed
public function refresh()
{
    $this->items = Item::where('updated_at', '>', $this->lastCheck)
        ->with('user')
        ->get();
    
    $this->lastCheck = now();
}
```

### Caching

```php
use Illuminate\Support\Facades\Cache;

public function refreshStats()
{
    $this->stats = Cache::remember('dashboard-stats', 5, function() {
        return [
            'users' => User::count(),
            'orders' => Order::count(),
            'revenue' => Order::sum('total'),
        ];
    });
}
```

### Conditional Updates

```php
public function checkStatus()
{
    $order = Order::find($this->orderId);
    
    // Only update if status changed
    if ($order->status !== $this->status) {
        $this->status = $order->status;
    }
}
```

## Combining with Other Features

### Polling + Search

```blade
<div>
    <input 
        diffyne:model.live.debounce.300="search"
        placeholder="Search...">
    
    <div diff:poll="10000" diff:poll.action="refresh">
        @foreach($results as $result)
            <div>{{ $result->name }}</div>
        @endforeach
    </div>
</div>
```

### Polling + Loading States

```blade
<div diff:poll="5000" diff:poll.action="refresh">
    <div>
        {{-- Content --}}
    </div>
    
    <div diffyne:loading>
        Updating...
    </div>
</div>
```

### Polling + Manual Refresh

```blade
<div>
    <button diff:click="refresh" class="mb-4">
        Refresh Now
    </button>
    
    <div diff:poll="30000" diff:poll.action="refresh">
        {{-- Content that auto-refreshes every 30s --}}
        {{-- Or can be manually refreshed --}}
    </div>
</div>
```

## Best Practices

### 1. Choose Appropriate Intervals

```blade
{{-- Fast updates for critical data (1 second = 1000ms) --}}
<div diff:poll="1000" diff:poll.action="checkOrderStatus">

{{-- Moderate updates for dashboards (5 seconds = 5000ms) --}}
<div diff:poll="5000" diff:poll.action="refreshStats">

{{-- Slow updates for less critical data (30 seconds = 30000ms) --}}
<div diff:poll="30000" diff:poll.action="checkNotifications">
```

### 2. Stop Polling When Done

```blade
{{-- Only poll while processing --}}
<div @if($status === 'processing') diff:poll="2000" diff:poll.action="checkStatus" @endif>
```

### 3. Show Loading State

```blade
<div diff:poll="5000" diff:poll.action="refresh">
    {{-- Content --}}
    
    <small diffyne:loading class="text-gray-500">
        Updating...
    </small>
</div>
```

### 4. Optimize Database Queries

```php
// Use efficient queries
public function refresh()
{
    $this->count = Order::whereDate('created_at', today())->count();
}

// Consider caching
public function refresh()
{
    $this->data = Cache::remember('key', 5, fn() => expensiveQuery());
}
```

### 5. Handle Errors Gracefully

```php
public function refresh()
{
    try {
        $this->data = $this->fetchData();
    } catch (\Exception $e) {
        // Log error but don't break polling
        logger()->error('Polling failed', ['error' => $e->getMessage()]);
    }
}
```

## Troubleshooting

### High Server Load

- Increase poll interval
- Add caching
- Optimize database queries
- Limit data fetched

### Polling Not Working

- Check browser console for errors
- Ensure method exists in component
- Verify polling syntax (e.g., `5s` not `5`)

### Memory Leaks

- Clear old data in method
- Limit collection size
- Use pagination for large datasets

## Next Steps

- [Loading States](loading-states.md) - Show loading feedback
- [Click Events](click-events.md) - Manual refresh buttons
- [Examples](../examples/) - See polling in action
- [Performance](../advanced/performance.md) - Optimization tips
