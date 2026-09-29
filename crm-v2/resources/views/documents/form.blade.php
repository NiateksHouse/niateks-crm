@extends('layout')
@section('content')
<a href="{{ $document ? route('documents.show',$document->id) : route('documents.index') }}">← Dokümanlar</a><h1>{{ $document ? 'Belge bilgilerini düzenle' : 'Yeni Doküman' }}</h1>
<form class="card" method="post" enctype="multipart/form-data" action="{{ $document ? route('documents.update',$document->id) : route('documents.store') }}">@csrf @if($document) @method('PUT')<input type="hidden" name="revision" value="{{ $document->revision }}">@endif
<label for="document-name">Doküman adı</label><input id="document-name" name="name" value="{{ old('name',$document?->name) }}" required maxlength="180">
<label for="category">Kategori</label><select id="category" name="category_id" required><option value="">Kategori seçin</option>@include('documents.category-options',['nodes'=>$tree,'depth'=>0,'selected'=>old('category_id',$document?->category_id)])</select>
<label for="description">Açıklama / not</label><textarea id="description" name="description" maxlength="5000">{{ old('description',$document?->description) }}</textarea>
<div class="document-date-fields"><div><label for="document-date">Belge tarihi</label><input type="date" id="document-date" name="document_date" value="{{ old('document_date',$document?->document_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required></div><div><label for="expires">Geçerlilik / son kullanma tarihi (isteğe bağlı)</label><input type="date" id="expires" name="expires_at" value="{{ old('expires_at',$document?->expires_at?->format('Y-m-d')) }}"></div></div>
@if(!$document) @include('documents.access-fields') @include('documents.file-fields') @else<p class="muted">Dosyayı değiştirmek için belge detayından “Yeni Sürüm Yükle”yi kullanın. Eski dosya korunur. Erişim izinleri ayrı bölümden yönetilir.</p>@endif<button class="primary">{{ $document ? 'Bilgileri kaydet' : 'Dokümanı kaydet' }}</button></form>
@endsection
