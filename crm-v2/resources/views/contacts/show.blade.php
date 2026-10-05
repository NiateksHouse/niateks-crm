@extends('layout')
@section('content')<a href="{{ route('contacts.index') }}">← Kişiler</a><h1>{{ $contact->name }}</h1><section class="card"><p><a href="{{ route('companies.show',$contact->company) }}">{{ $contact->company->name }}</a></p><p>{{ $contact->email ?: 'E-posta eklenmedi' }}</p><p>{{ $contact->phone ?: 'Telefon eklenmedi' }}</p></section>@endsection
