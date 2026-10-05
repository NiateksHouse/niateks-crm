@extends('layout')
@section('content')
<div class="page-heading"><div><h1>Görüşmeler</h1><p class="muted">Firma ve proje görüşmeleri aynı zaman akışında; yalnız yetkiniz olan kayıtlar.</p></div><a class="button primary" href="{{ route('companies.index') }}">Görüşme eklemek için firma seç</a></div>
@include('partials.timeline')
@endsection
