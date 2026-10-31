@props(['icon' => 'box', 'title', 'description' => null])
<div class="cp-empty">
    <span class="cp-empty-icon"><x-portal.icon :name="$icon" /></span>
    <div><h3>{{ $title }}</h3>@if($description)<p>{{ $description }}</p>@endif</div>
    @if($slot->isNotEmpty())<div class="cp-empty-actions">{{ $slot }}</div>@endif
</div>
