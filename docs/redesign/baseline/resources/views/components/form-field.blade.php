@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false, 'help' => null])
<label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span>@endif</label>
<input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }} @required($required) @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @elseif($help) aria-describedby="{{ $name }}-help" @endif>
@error($name)<div id="{{ $name }}-error" class="invalid-feedback">{{ $message }}</div>@enderror
@if($help)<div id="{{ $name }}-help" class="form-text">{{ $help }}</div>@endif
