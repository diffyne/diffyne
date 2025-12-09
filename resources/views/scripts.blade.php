{{-- 
    Diffyne Scripts
    Include this in your layout before closing </body> tag
--}}

<script>
    window.diffyneConfig = {
        transport: '{{ config('diffyne.transport', 'ajax') }}',
        wsUrl: '{{ config('diffyne.transport') === 'websocket' ? 'ws://' . config('diffyne.websocket.host', '127.0.0.1') . ':' . config('diffyne.websocket.port', 6001) : '' }}?key={{ config('diffyne.websocket.key', null) }}',
        wsPort: {{ config('diffyne.websocket.port', 6001) }},
        endpoint: '{{ config('diffyne.endpoint', '/_diffyne') }}',
        debug: {{ config('diffyne.debug', false) ? 'true' : 'false' }},
        maxMessageSize: {{ config('diffyne.websocket.max_message_size', 1048576) }}
    };
</script>
<script src="{{ config('diffyne.asset_url', '/vendor/diffyne') }}/diffyne.js" defer></script>
