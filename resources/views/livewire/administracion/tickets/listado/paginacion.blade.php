@if($tickets->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $tickets->links() }}</div>
@endif
