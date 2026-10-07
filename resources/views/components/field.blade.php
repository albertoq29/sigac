@props(['label' => null, 'name' => null, 'help' => null, 'required' => false])
<div {{ $attributes->class('min-w-0') }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="label">{{ $label }}@if ($required) <span class="text-rose-500">*</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($help)
        <p class="help-text">{{ $help }}</p>
    @endif
    @if ($name)
        @error($name)
            <p class="error-text">{{ $message }}</p>
        @enderror
    @endif
</div>
