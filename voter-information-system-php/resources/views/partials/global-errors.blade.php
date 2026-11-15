@if(isset($errors) && $errors->has('global'))<div class="col-12 alert alert-danger py-2 mb-0">@foreach($errors->get('global') as $e)<div>{{ $e }}</div>@endforeach</div>@endif
