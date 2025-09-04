<div class="space-y-4">
    <h3 class="text-lg font-semibold">Transaction Details</h3>
    <pre class="bg-gray-900 text-white p-4 rounded-lg text-sm overflow-auto">
{{ json_encode($record->payload, JSON_PRETTY_PRINT) }}
    </pre>
</div>
