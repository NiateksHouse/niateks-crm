@extends('layout')
@section('content')<h1>Yeni kişi</h1><form class="card" method="post" action="{{ route('contacts.store') }}">@csrf<label>Firma<select name="company_id" required>@foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></label>
@foreach(['name'=>'Ad soyad','email'=>'E-posta','phone'=>'Telefon (ülke koduyla)'] as $key=>$label)<label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" name="{{ $key }}" value="{{ old($key) }}" @required($key==='name') type="{{ $key==='email'?'email':'text' }}">@endforeach
<button name="intent" value="save">Kişiyi kaydet</button><button name="intent" value="review">Önce eşleşmeleri incele</button></form>@endsection
