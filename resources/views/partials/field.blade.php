{{-- Uso: @include('partials.field', ['name' => 'x', 'label' => 'X', 'value' => ..., 'type' => 'text', 'hint' => null, 'required' => false]) --}}
@php($type = $type ?? 'text')
<div class="field">
    <label for="f-{{ $name }}">{{ $label }}@if (!empty($required)) *@endif</label>
    @if ($type === 'textarea')
        <textarea id="f-{{ $name }}" name="{{ $name }}" @required(!empty($required))>{{ old($name, $value ?? '') }}</textarea>
    @else
        <input id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value ?? '') }}"
               @if ($type === 'number') step="{{ $step ?? 'any' }}" min="{{ $min ?? '' }}" @endif
               @required(!empty($required)) {{ $attrs ?? '' }}>
    @endif
    @if (!empty($hint))<div class="hint">{{ $hint }}</div>@endif
    @error($name)<div class="err">{{ $message }}</div>@enderror
</div>
