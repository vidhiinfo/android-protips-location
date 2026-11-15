@include('partials.flash')
@if($silence)<div class="alert alert-danger py-2"><i class="bi bi-slash-circle"></i> {{ t('msg.silenceActive', $silence) }}</div>@endif
