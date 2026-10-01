@props(['name','label','value'=>null,'type'=>'text','options'=>null,'required'=>false])
<div class="mb-3">
<label class="form-label" for="{{ $name }}">{{ $label }} @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
@if($options !== null)
<select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['form-select','is-invalid'=>$errors->has($name)]) }} @required($required)>
@foreach($options as $key=>$text)<option value="{{ $key }}" @selected((string)old($name,$value)===(string)$key)>{{ $text }}</option>@endforeach
</select>
@elseif($type === 'textarea')
<textarea id="{{ $name }}" name="{{ $name }}" rows="5" {{ $attributes->class(['form-control','is-invalid'=>$errors->has($name)]) }} @required($required)>{{ old($name,$value) }}</textarea>
@else
<input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'file') value="{{ old($name,$value) }}" @endif {{ $attributes->class(['form-control','is-invalid'=>$errors->has($name)]) }} @required($required)>
@endif
@error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

